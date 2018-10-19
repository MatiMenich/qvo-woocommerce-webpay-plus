=== Woocommerce QVO Payment Gateway Plugin ===
Contributors: qvo-team, matimenich, uribefache, brunocalderon
Tags: woocommerce, payment, chile, qvo, webpay, pago, redcompra, transbank
Stable tag: 1.3.1
Requires at least: 4.4
Tested up to: 4.9.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Añade a QVO como método de pago para WooCommerce.

== Description ==
Añade a [QVO](https://qvo.cl) como método de pago para Woocommerce.

Utiliza la API de QVO para realizar pagos a través de Woocommerce.

El soporte al plugin se realiza directamente en [GitHub](https://github.com/qvo-team/qvo-woocommerce-webpay-plus/issues).

== Installation ==
1. Ingresa a tu Administrador (WP-Admin), luego Plugins -> Añadir Nuevo. Busca "Woocommerce QVO Payment". Presiona "Instalar ahora" y luego Actívalo.
También puedes instalarlo de forma manual: sube el plugin a tu WordPress y actívalo.
2. Luego ve a WooCommerce -> Ajustes -> Finalizar Compra -> QVO – Pago a través de Webpay Plus.
3. Configura las opciones. Si no tienes tus credenciales, obtenlas en tu Dashboard QVO. Si no tienes una cuenta obtenla [aquí](https://qvo.cl)
4. Listo.

Ahora tus clientes podrán seleccionar QVO para pagar con Webpay Plus sus productos.

== Frequently Asked Questions ==
= ¿Dónde consigo una cuenta?.
Para obtener una cuenta, regístrate en [QVO](https://qvo.cl).

= ¿Errores? ¿Sugerencias?
Reportar errores y enviar sugerencias directamente en [GitHub](https://github.com/qvo-team/qvo-woocommerce-webpay-plus/issues), por favor.

Ayuda y aportes (pull requests) son bienvenidos.

¡Gracias!

== Screenshots ==
1. Pago con tarjeta en Finalizar Compra.
2. Configuración del plugin.

== Changelog ==

= 1.3.1 =
* Arreglado problemas de compatibilidad con versiones anteriores de PHP.

= 1.3.0 =
* NUEVO: Crea clientes en QVO a partir de datos ingresados. Útil para hacer seguimiento en el panel de las transacciones asociadas a un cliente en particular.
* Refactorización del flujo de pago.
* Arregla problemas para el reintentos de pagos fallidos.
* Arregla bugs en el envío de correos de confirmación para cliente y comercio.

= 1.2.6 =
* Arregla problemas con la configuración de las monedas.

= 1.2.5 =
* Mejoras en el manejo de la configuración: Checkeo de credenciales y acceso fácil

= 1.2.4 =
* Extiende capacidades del plugin.
* Arregla bug en reintento de pago.

= 1.2.3 =
* Arregla problemas de compatibilidad con Woocommerce 3.2.+

= 1.2.2 =
* Arregla problemas de incompatibilidad con PHP 7 y otros.

= 1.2.1 =
* Añadidas descripciones a menú de configuración.

= 1.2.0 =
* Refactor de librerías.

= 1.1.0 =
* Primera versión pública.

== Upgrade Notice ==
Activar y configurar.