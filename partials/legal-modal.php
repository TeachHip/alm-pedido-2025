<?php
// partials/legal-modal.php - "Aviso legal y Política de privacidad" modal,
// included once from partials/footer.php (present on every front-end page).
// Content reviewed and finalized by the user 2026-08-17. Structure follows
// LOPDGDD's minimum notice requirements (responsable, finalidad, base legal,
// destinatarios, plazo de conservación, derechos).
require_once __DIR__ . '/../includes/repositories/SettingsRepository-DB.php';
$legalSettingsRepo = new SettingsRepository();
$legalBusinessName = $legalSettingsRepo->get('business_name', 'AlMercáu');
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
            AlMercáu es un Grupo de Consumo del barrio de Laviada en Gijón, donde compramos nuestros productos
            directamente del productor, sin intermediarios, para comerciar de forma más justa y comer mejor.
            Esta aplicación sólo existe para facilitar la gestión de pedidos: llevar la cuenta de quién
            pide qué, generar el ticket de compra y avisar de que el pedido está listo. <strong>No vendemos ni
            compartimos tus datos con nadie ajeno a ese proceso, ni nos interesa</strong>. Al darte de alta te
            explicamos cómo funciona el proceso de compra y recogida, y te comprometes a emplear esta aplicación con buena fe para el uso explicitado.
        </p>

        <h3>Responsable del tratamiento</h3>
        <p>
            <?php echo htmlspecialchars($legalBusinessName); ?> |
            NIF <?php echo htmlspecialchars($legalBusinessNif); ?> |
            <?php echo htmlspecialchars($legalBusinessAddress); ?>
            <br>Contacto en persona en el local de AlMercáu, por los canales habituales del grupo o en el correo almercau@gmail.com.
        </p>

        <h3>Qué datos tratamos y para qué</h3>
        <p>
            Sólo necesitamos tu teléfono y un alias a tu elección (no necesitamos más datos personales, nos conocemos). Además estamos obligados a retener el historial de pedidos y tickets de compra. Estos datos se usan únicamente
            para gestionar tu condición de mercante (socio del Grupo de Consumo), procesar tus pedidos, generar el ticket de compra
            correspondiente y avisarte de que el pedido está disponible o de cualquier circunstancia especial que ocurra en el proceso. No se usan con fines publicitarios ni se
            ceden a terceros para otros fines.
        </p>

        <h3>Base legal</h3>
        <p>
            El tratamiento es necesario para gestionar tu relación como mercante y para
            cumplir con la gestión de tus pedidos.
        </p>

        <h3>Con quién compartimos tus datos</h3>
        <p>
            Con los proveedores técnicos estrictamente necesarios para prestar el servicio: la pasarela de pago
            (para generar el enlace de cobro de tu ticket) y el proveedor de SMS (para avisarte de que tu ticket
            está listo). Ambos actúan como encargados del tratamiento, sólo con los datos mínimos
            necesarios (teléfono e importe), y no pueden usarlos para nada distinto.
        </p>

        <h3>Cuánto tiempo conservamos tus datos</h3>
        <p>
            Tus datos como mercante, mientras lo seas. Los tickets de compra, el tiempo exigido por la normativa fiscal y mercantil aplicable. Si necesitas revisarlos, pídelo.
        </p>

        <h3>Tus derechos</h3>
        <p>
            Puedes pedir acceder, corregir o borrar tus datos, o limitar/oponerte a su uso, hablándolo
            directamente con AlMercáu por cualquiera de las vías anteriormente especificadas. Somos un grupo pequeño y cercano, no hace falta ningún
            trámite formal.
        </p>

        <h3>Ticket de compra y factura</h3>
        <p>Tienes derecho a solicitar factura ordinaria tras aportar tus datos fiscales con tiempo suficiente para su emisión dentro del trimestre fiscal por el que nos regimos.</p>

    </div>
    <div class="legal-modal-footer">
        <button type="button" id="legal-modal-close-btn" class="btn">Cerrar</button>
    </div>
</div>
