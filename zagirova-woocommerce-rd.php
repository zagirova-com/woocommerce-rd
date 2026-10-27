<?php
/**
 * Plugin Name: WooCommerce RD (Zagirova)
 * Description: Lo que una tienda WooCommerce en República Dominicana necesita en el checkout: campo de RNC o cédula con dígito verificador, tipo de comprobante (consumo o crédito fiscal), teléfono con código de área dominicano y ITBIS al 18 %. Sin dependencias.
 * Version: 1.0.0
 * Author: Zagirova
 * Author URI: https://zagirova.com
 * License: MIT
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) exit;

/* ---------- Validación de formato (misma regla que github.com/zagirova/validadores-rd) ---------- */

function zrd_limpiar($s) { return preg_replace('/[\s-]/', '', (string) $s); }

/** Cédula: 11 dígitos, Luhn sobre los 10 primeros. */
function zrd_cedula_valida($cedula) {
    $c = zrd_limpiar($cedula);
    if (!preg_match('/^\d{11}$/', $c)) return false;
    $suma = 0;
    for ($i = 0; $i < 10; $i++) {
        $n = (int) $c[$i] * ($i % 2 === 0 ? 1 : 2);
        if ($n > 9) $n = intdiv($n, 10) + ($n % 10);
        $suma += $n;
    }
    return (10 - ($suma % 10)) % 10 === (int) $c[10];
}

/** RNC: 9 dígitos, pesos 7 9 8 6 5 4 3 2, módulo 11. */
function zrd_rnc_valido($rnc) {
    $r = zrd_limpiar($rnc);
    if (!preg_match('/^\d{9}$/', $r)) return false;
    $pesos = [7, 9, 8, 6, 5, 4, 3, 2]; $suma = 0;
    foreach ($pesos as $i => $p) $suma += $p * (int) $r[$i];
    $resto = $suma % 11;
    $dv = $resto === 0 ? 2 : ($resto === 1 ? 1 : 11 - $resto);
    return $dv === (int) $r[8];
}

/** Teléfono dominicano (809, 829, 849) en E.164, o null. */
function zrd_telefono($raw) {
    $d = preg_replace('/\D/', '', (string) $raw); $d = preg_replace('/^1/', '', $d);
    return preg_match('/^(809|829|849)\d{7}$/', $d) ? '+1' . $d : null;
}

/* ---------- Campos del checkout ---------- */

add_filter('woocommerce_checkout_fields', function ($fields) {
    $fields['billing']['billing_rnc'] = [
        'label'       => 'RNC o cédula',
        'placeholder' => '101-00001-1 o 001-0000000-0',
        'required'    => false,
        'class'       => ['form-row-wide'],
        'priority'    => 25,
    ];
    $fields['billing']['billing_comprobante'] = [
        'type'     => 'select',
        'label'    => 'Comprobante fiscal',
        'required' => true,
        'class'    => ['form-row-wide'],
        'priority' => 26,
        'default'  => 'consumo',
        'options'  => [
            'consumo' => 'Factura de consumo (B02 / E32)',
            'credito' => 'Factura de crédito fiscal (B01 / E31): requiere RNC',
        ],
    ];
    $fields['billing']['billing_phone']['placeholder'] = '809 000 0000';
    return $fields;
});

add_action('woocommerce_checkout_process', function () {
    $rnc  = isset($_POST['billing_rnc']) ? zrd_limpiar(wc_clean(wp_unslash($_POST['billing_rnc']))) : '';
    $tipo = isset($_POST['billing_comprobante']) ? wc_clean(wp_unslash($_POST['billing_comprobante'])) : 'consumo';
    if ($tipo === 'credito' && $rnc === '') {
        wc_add_notice('Para una factura de crédito fiscal hace falta el RNC o la cédula.', 'error');
    }
    if ($rnc !== '' && !zrd_rnc_valido($rnc) && !zrd_cedula_valida($rnc)) {
        wc_add_notice('El RNC o la cédula no tiene un formato válido (revise los dígitos).', 'error');
    }
    $tel = isset($_POST['billing_phone']) ? wc_clean(wp_unslash($_POST['billing_phone'])) : '';
    if ($tel !== '' && zrd_telefono($tel) === null) {
        wc_add_notice('El teléfono debe ser dominicano: 809, 829 u 849 y diez dígitos.', 'error');
    }
});

add_action('woocommerce_checkout_update_order_meta', function ($order_id) {
    if (!empty($_POST['billing_rnc'])) update_post_meta($order_id, '_billing_rnc', zrd_limpiar(wc_clean(wp_unslash($_POST['billing_rnc']))));
    if (!empty($_POST['billing_comprobante'])) update_post_meta($order_id, '_billing_comprobante', wc_clean(wp_unslash($_POST['billing_comprobante'])));
    if (!empty($_POST['billing_phone'])) { $t = zrd_telefono(wc_clean(wp_unslash($_POST['billing_phone']))); if ($t) update_post_meta($order_id, '_billing_phone_e164', $t); }
});

/* El RNC y el tipo de comprobante en el panel del pedido y en los correos. */
add_action('woocommerce_admin_order_data_after_billing_address', function ($order) {
    $rnc = get_post_meta($order->get_id(), '_billing_rnc', true); $tipo = get_post_meta($order->get_id(), '_billing_comprobante', true);
    if ($rnc) echo '<p><strong>RNC o cédula:</strong> ' . esc_html($rnc) . '</p>';
    if ($tipo) echo '<p><strong>Comprobante:</strong> ' . esc_html($tipo === 'credito' ? 'Crédito fiscal (B01 / E31)' : 'Consumo (B02 / E32)') . '</p>';
});
add_filter('woocommerce_email_order_meta_fields', function ($fields, $sent_to_admin, $order) {
    $rnc = get_post_meta($order->get_id(), '_billing_rnc', true);
    if ($rnc) $fields['billing_rnc'] = ['label' => 'RNC o cédula', 'value' => $rnc];
    return $fields;
}, 10, 3);

/* ---------- ITBIS 18 % ---------- */
/* WooCommerce ya calcula impuestos; esto crea la tasa estándar de RD una sola vez si no existe
   (Ajustes → Impuestos → Tasas estándar) y deja los precios tal como los introduce el comerciante. */
add_action('init', function () {
    if (get_option('zrd_itbis_creado') || !class_exists('WC_Tax')) return;
    $existentes = WC_Tax::get_rates_for_tax_class('');
    foreach ($existentes as $r) { if ($r->tax_rate_country === 'DO') { update_option('zrd_itbis_creado', 1); return; } }
    WC_Tax::_insert_tax_rate(['tax_rate_country' => 'DO', 'tax_rate' => '18.0000', 'tax_rate_name' => 'ITBIS', 'tax_rate_priority' => 1, 'tax_rate_compound' => 0, 'tax_rate_shipping' => 1, 'tax_rate_order' => 0, 'tax_rate_class' => '']);
    update_option('zrd_itbis_creado', 1);
});
