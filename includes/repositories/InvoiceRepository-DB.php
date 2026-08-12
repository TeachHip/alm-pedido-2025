<?php
/**
 * Invoice Repository
 * Handles the "ticket de compra" (simplified receipt, not a legal fiscal
 * factura) shown on a token-secured public page and sent by SMS. Deliberately
 * decoupled from pricing/membership logic -- create() only ever persists
 * already-finalized numbers handed to it by the caller. See AI/plans v10.
 */

require_once __DIR__ . '/../db/database-DB.php';
require_once __DIR__ . '/../TicketNumberHelper.php';

class InvoiceRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Ticket number in the same #ALM-YYYY-MM-#### format as
     * CartRepository::generateTicketNumber(), counted against invoices
     * (not carts) since a correction can add an invoice without a new cart.
     */
    private function generateTicketNumber() {
        $year = date('Y');
        $month = date('m');

        $sql = "SELECT COUNT(*) as count FROM invoices
                WHERE DATE_FORMAT(created_at, '%Y-%m') = :year_month";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['year_month' => "{$year}-{$month}"]);
        $result = $stmt->fetch();

        $nextNumber = ($result['count'] ?? 0) + 1;
        return formatTicketNumber($year, $month, $nextNumber);
    }

    /**
     * Create a new invoice + its line items in one transaction.
     * $data: member_id, cart_id (nullable), items ([product_name, option_label,
     * quantity, unit_price, line_total]...), subtotal, surcharge_amount,
     * surcharge_label, total_amount, due_date, supersedes_invoice_id (nullable).
     * Returns ['success', 'invoice_id', 'token', 'ticket_number'] or ['success' => false, 'error'].
     */
    public function create($data) {
        try {
            $this->db->beginTransaction();

            $token = bin2hex(random_bytes(16));
            $ticketNumber = $this->generateTicketNumber();

            $sql = "INSERT INTO invoices
                    (member_id, cart_id, ticket_number, token, supersedes_invoice_id,
                     subtotal, surcharge_amount, surcharge_label, total_amount, due_date, created_at)
                    VALUES
                    (:member_id, :cart_id, :ticket_number, :token, :supersedes_invoice_id,
                     :subtotal, :surcharge_amount, :surcharge_label, :total_amount, :due_date, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'member_id' => $data['member_id'],
                'cart_id' => $data['cart_id'] ?? null,
                'ticket_number' => $ticketNumber,
                'token' => $token,
                'supersedes_invoice_id' => $data['supersedes_invoice_id'] ?? null,
                'subtotal' => $data['subtotal'],
                'surcharge_amount' => $data['surcharge_amount'] ?? null,
                'surcharge_label' => $data['surcharge_label'] ?? null,
                'total_amount' => $data['total_amount'],
                'due_date' => $data['due_date'],
            ]);

            $invoiceId = $this->db->lastInsertId();

            $itemSql = "INSERT INTO invoice_items
                        (invoice_id, product_name, option_label, quantity, unit_price, iva_rate, line_total, display_order)
                        VALUES (:invoice_id, :product_name, :option_label, :quantity, :unit_price, :iva_rate, :line_total, :display_order)";
            $itemStmt = $this->db->prepare($itemSql);

            foreach ($data['items'] as $index => $item) {
                $itemStmt->execute([
                    'invoice_id' => $invoiceId,
                    'product_name' => $item['product_name'],
                    'option_label' => $item['option_label'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'iva_rate' => $item['iva_rate'] ?? null,
                    'line_total' => $item['line_total'],
                    'display_order' => $index,
                ]);
            }

            $this->db->commit();

            return [
                'success' => true,
                'invoice_id' => $invoiceId,
                'token' => $token,
                'ticket_number' => $ticketNumber,
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error creating invoice: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Look up an invoice by its public token, with the member's alias/phone/
     * member_number joined in (the page needs all three, and this keeps
     * ticket.php from having to round-trip to MemberRepository separately).
     */
    public function findByToken($token) {
        $sql = "SELECT i.*, m.alias as member_alias, m.phone as member_phone, m.member_number as member_number
                FROM invoices i
                JOIN members m ON i.member_id = m.id
                WHERE i.token = :token
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['token' => $token]);
        return $stmt->fetch();
    }

    public function findById($id) {
        $sql = "SELECT * FROM invoices WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Most recent active invoice for a cart, if any -- used to decide
     * whether admin/orders.php should offer "Crear ticket" or "Ver ticket".
     */
    public function findByCartId($cartId) {
        $sql = "SELECT * FROM invoices WHERE cart_id = :cart_id ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cart_id' => $cartId]);
        return $stmt->fetch();
    }

    public function getItems($invoiceId) {
        $sql = "SELECT * FROM invoice_items WHERE invoice_id = :invoice_id ORDER BY display_order ASC, id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['invoice_id' => $invoiceId]);
        return $stmt->fetchAll();
    }

    /**
     * All invoices for a member, most recent first (for a future member
     * account page -- not built in Stage 1, but the repository supports it).
     */
    public function getByMemberId($memberId) {
        $sql = "SELECT * FROM invoices WHERE member_id = :member_id ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['member_id' => $memberId]);
        return $stmt->fetchAll();
    }

    /**
     * Reissue/correction: creates a new invoice referencing the old one via
     * supersedes_invoice_id, and marks the old one superseded with a forward
     * pointer -- never edits the old row's content. $newData is the same
     * shape as create()'s $data (minus supersedes_invoice_id, set here).
     */
    public function supersede($oldInvoiceId, $newData) {
        $newData['supersedes_invoice_id'] = $oldInvoiceId;
        // create() runs its own self-contained transaction for the new
        // invoice + items; the old row's status flip is a separate, single
        // atomic UPDATE, so no outer transaction is needed here.
        $result = $this->create($newData);

        if (!$result['success']) {
            return $result;
        }

        try {
            $stmt = $this->db->prepare("UPDATE invoices SET status = 'superseded', superseded_by_invoice_id = :new_id WHERE id = :old_id");
            $stmt->execute(['new_id' => $result['invoice_id'], 'old_id' => $oldInvoiceId]);
        } catch (Exception $e) {
            error_log("Error marking old invoice superseded: " . $e->getMessage());
            // The new invoice was created successfully; this is a secondary
            // bookkeeping failure, not reported as an overall failure -- but logged.
        }

        return $result;
    }

    /**
     * Set the payment link/reference (real PayGold, or the mock stand-in --
     * see includes/InvoiceHelper.php).
     */
    public function setPaymentUrl($invoiceId, $paymentUrl, $reference = null) {
        $sql = "UPDATE invoices SET paygold_payment_url = :url, paygold_reference = :reference WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $invoiceId, 'url' => $paymentUrl, 'reference' => $reference]);
    }

    public function markPaid($invoiceId) {
        $sql = "UPDATE invoices SET payment_status = 'paid', paid_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $invoiceId]);
    }

    public function markSmsSent($invoiceId) {
        $sql = "UPDATE invoices SET sms_sent_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $invoiceId]);
    }

    public function cancel($invoiceId) {
        $sql = "UPDATE invoices SET status = 'cancelled' WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $invoiceId]);
    }
}
