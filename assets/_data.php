<?php
// Simplified product data structure - no manual ID needed!
$products = array(
    'bakery' => array(
        array(
            'name' => 'Setas Shiitake de roble ECO SetaDeBosque',
            'price' => 9.00,
            'image' => 'primgs/setas.jpg',
            'description' => 'Precio por medio kilo.

Shiitake de roble ecológicas, setas frescas de Piloña. La Shiitake es una seta de origen asiático, sabor intenso y numerosas propiedades beneficiosas. En tiendas es fácil encontrarla deshidratada pero en este caso es fresca y recién recolectada. Conservación para varios días o semanas (se va secando pero dura mucho) en nevera.

18€/kg.'
        ),
        array(
            'name' => 'Patata gallega Kennebec - 3kg',
            'price' => 3.50,
            'image' => 'primgs/patata.jpg',
            'description' => 'Patata gallega para todo uso. Cuando tengamos más información sobre la variedad, usos... iré completando esta ficha.

Precio incluye descuento para mercantes/socias.
Precio unitario: 1,17€/kg IVA inc.'
        ),
        array(
            'name' => 'Huevos camperos M y L - 1/2 docena',
            'price' => 1.75,
            'image' => 'primgs/huevos.jpg',
            'description' => 'Huevos camperos de gallinas en semi-libertad de Granja La Amistad.'
        )
    ),
    'produce' => array(
        array(
            'name' => 'Cerveza artesana Sour – Cotoya',
            'price' => 2.50,
            'image' => 'primgs/sour.jpg',
            'description' => 'Una sour muy ligera de alcohol y súper refrescante..La mejor opción para disfrutar de una muy buena cerveza sin pasarse de la raya.

    Alc. 1,1% vol.
    Productor: Cotoya (Lugones)
    Formato: envase de vidrio 0,33l
    Envasado por el propio productor'
        ),
        array(
            'name' => 'Cerveza artesana bitter – Cotoya',
            'price' => 2.50,
            'image' => 'primgs/cotoya-original.jpg',
            'description' => '¿La mejor cerveza hecha en Asturias para tomar a diario? A nosotras nos parece que sí, pero para gustos...'
        )
    )
);

// Section definitions
$sections = array(
    'bakery' => 'Pedido de Grupo',
    'produce' => 'Cervezas'
);

// Section images - ADD THIS ARRAY
$sectionImages = array(
    'bakery' => 'primgs/pedido-de-grupo.jpg',      // Image for bakery section
    'produce' => 'primgs/cervezas.jpg' // Image for produce section
);

// Helper function to generate unique IDs automatically

// Helper function to generate unique IDs automatically
if (!function_exists('getProductId')) {
    function getProductId($sectionKey, $productIndex) {
        return "{$sectionKey}_{$productIndex}";
    }
}

// Helper function to get a product by generated ID
if (!function_exists('getProductById')) {
    function getProductById($id) {
        global $products;
        list($sectionKey, $productIndex) = explode('_', $id);
        return $products[$sectionKey][$productIndex] ?? null;
    }
}
?>