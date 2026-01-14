<?php
require_once '/var/www/html/vendor/autoload.php';

use Transbank\Webpay\Options;

echo class_exists(Options::class) ? "✅ Clase cargada" : "❌ Clase NO cargada";
