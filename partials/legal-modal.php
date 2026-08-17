<?php
// partials/legal-modal.php - "Aviso legal y Política de privacidad" modal,
// included once from partials/footer.php (present on every front-end page).
// Content is a DRAFT -- flagged by the user as needing their own review/
// rewrite before this is considered final. Structure follows LOPDGDD's
// minimum notice requirements (responsable, finalidad, base legal,
// destinatarios, plazo de conservación, derechos); the colloquial framing
// of AlMercáu's community aims is a starting point, not settled copy.
require_once __DIR__ . '/../includes/repositories/SettingsRepository-DB.php';
$legalSettingsRepo = new SettingsRepository();
$legalBusinessName = $legalSettingsRepo->get('business_name', 'AlMercáu');
$legalAssociationName = $legalSettingsRepo->get('association_name', '');
$legalBusinessNif = $legalSettingsRepo->get('business_nif', '');
$legalBusinessAddress = $legalSettingsRepo->get('business_address', '');
?>
<div id="legal-modal-backdrop" class="legal-modal-backdrop"></div>
<div id="legal-modal" class="legal-modal" role="dialog" aria-modal="true" aria-labelledby="legal-modal-title">
    <div class="legal-modal-header">
        <span id="legal-modal-title">Aviso legal y política de privacidad</span>
        <button type="button" id="legal-modal-close-x" class="legal-modal-close" aria-label="Cerrar">✕</button>
    </div>
    <div class="legal-modal-body">

        <h3>En pocas palabras</h3>
        <p>
            AlMerc&aacute;u<?php echo $legalAssociationName ? ' (' . htmlspecialchars($legalAssociationName) . ')' : ''; ?>
            es un grupo de consumo de barrio: un grupo de vecinas y vecinos de Laviada (Gij&oacute;n) que compra
            directamente a productoras locales, sin intermediarios, para comer mejor y apoyar al productor de
            cercan&iacute;a. Esta aplicaci&oacute;n solo existe para facilitar ese reparto: llevar la cuenta de qui&eacute;n
            pide qu&eacute;, generar el ticket de compra y avisar por SMS de que est&aacute; listo. No vendemos ni
            compartimos tus datos con nadie ajeno a ese proceso.
        </p>

        <h3>Responsable del tratamiento</h3>
        <p>
            <?php echo htmlspecialchars($legalBusinessName); ?><?php echo $legalBusinessNif ? ' (NIF ' . htmlspecialchars($legalBusinessNif) . ')' : ''; ?>.
            <?php if ($legalBusinessAddress): ?><?php echo htmlspecialchars($legalBusinessAddress); ?>.<?php endif; ?>
            Contacto: a trav&eacute;s de cualquier persona del equipo de AlMerc&aacute;u, en persona o por los canales
            habituales del grupo.
        </p>

        <h3>Qu&eacute; datos tratamos y para qu&eacute;</h3>
        <p>
            Tel&eacute;fono, nombre/alias y el historial de tus pedidos y tickets de compra. Los usamos &uacute;nicamente
            para gestionar tu condici&oacute;n de socia/o, procesar tus pedidos, generar el ticket de compra
            correspondiente y avisarte por SMS de que est&aacute; disponible. No se usan con fines publicitarios ni se
            ceden a terceros para otros fines.
        </p>

        <h3>Base legal</h3>
        <p>
            El tratamiento es necesario para gestionar tu relaci&oacute;n como socia/o del grupo de consumo y para
            cumplir con la gesti&oacute;n de tus pedidos -- no se basa en tu consentimiento para publicidad, porque no
            hacemos publicidad.
        </p>

        <h3>Con qui&eacute;n compartimos tus datos</h3>
        <p>
            Con los proveedores t&eacute;cnicos estrictamente necesarios para prestar el servicio: la pasarela de pago
            (para generar el enlace de cobro de tu ticket) y el proveedor de SMS (para avisarte de que tu ticket
            est&aacute; listo). Ambos act&uacute;an como encargados del tratamiento, solo con los datos m&iacute;nimos
            necesarios (tel&eacute;fono e importe), y no pueden usarlos para nada distinto.
        </p>

        <h3>Cu&aacute;nto tiempo conservamos tus datos</h3>
        <p>
            Los datos de socia/o, mientras lo seas. Los tickets de compra, el tiempo exigido por la normativa fiscal
            y mercantil aplicable (varios a&ntilde;os desde su emisi&oacute;n).
        </p>

        <h3>Tus derechos</h3>
        <p>
            Puedes pedir acceder, corregir o borrar tus datos, o limitar/oponerte a su uso, habl&aacute;ndolo
            directamente con AlMerc&aacute;u -- somos un grupo peque&ntilde;o y cercano, no hace falta ning&uacute;n
            tr&aacute;mite formal.
        </p>

    </div>
    <div class="legal-modal-footer">
        <button type="button" id="legal-modal-close-btn" class="btn">Cerrar</button>
    </div>
</div>
