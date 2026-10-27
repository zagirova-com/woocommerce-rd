# woocommerce-rd

Lo que una tienda WooCommerce en República Dominicana necesita en el checkout y no trae de fábrica, en un plugin de un solo archivo, sin dependencias:

- **RNC o cédula** en la facturación, con **dígito verificador** (misma regla que [validadores-rd](https://github.com/zagirova/validadores-rd)): si el cliente se equivoca en un dígito, el checkout lo dice.
- **Tipo de comprobante fiscal**: factura de consumo (B02 / E32) o de crédito fiscal (B01 / E31). La de crédito fiscal exige RNC.
- **Teléfono dominicano** (809, 829, 849) validado y guardado también en formato E.164 para WhatsApp y SMS.
- **ITBIS 18 %** creado como tasa estándar de RD la primera vez, si la tienda no la tiene.
- El RNC y el comprobante aparecen en el pedido del panel y en los correos.

Lo mantiene [Zagirova](https://zagirova.com), que construye y opera tiendas en línea con cobro, facturación electrónica e inventario conectados. Si quiere la tienda completa, con e-CF emitiéndose sola ante la DGII: [zagirova.com/tienda-online](https://zagirova.com/tienda-online).

## Instalación

1. Descargue `zagirova-woocommerce-rd.php` y súbalo a `wp-content/plugins/woocommerce-rd/`.
2. Actívelo en Plugins. Requiere WooCommerce.
3. Compruebe en WooCommerce → Ajustes → Impuestos que los impuestos están activados y que aparece la tasa «ITBIS» para DO.

## Qué no hace

No emite comprobantes ni habla con la DGII: solo recoge y valida los datos que la facturación necesita. La emisión de e-CF es otra pieza (y es la que hacemos nosotros).

MIT · [Zagirova](https://zagirova.com) · hola@zagirova.com
