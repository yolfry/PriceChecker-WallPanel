<?php

// Desarrollado por YPW S.R.L 2026 info@clopri.com

// ========================================================================
// 1. VARIABLES DE CONFIGURACIÓN (Entorno Docker Compose / .env)
// ========================================================================

// -- Credenciales de Base de Datos --
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: 'price';

// -- Configuración General de la App --
$appCurrency = getenv('APP_CURRENCY') ?: 'RD$';
$appBranch = (int)(getenv('APP_BRANCH') ?: 1);

// -- Configuración de Precios Principales --
$appPriceTier = getenv('APP_PRICE_TIER') ?: 'precio1';

// -- Configuración de Impuestos --
$appApplyTax = filter_var(getenv('APP_APPLY_TAX') ?: false, FILTER_VALIDATE_BOOLEAN);

// -- Configuración de Redondeo --
// true/false: Define si el precio final se redondeará para no mostrar centavos.
$appRoundPrice = filter_var(getenv('APP_ROUND_PRICE') ?: false, FILTER_VALIDATE_BOOLEAN);

// -- Configuración de Precio al Detalle (Secundario) --
$appRetailPriceTier = getenv('APP_RETAIL_PRICE_TIER') ?: 'preciodetalle';
$appShowRetailPrice = filter_var(getenv('APP_SHOW_RETAIL_PRICE') ?: true, FILTER_VALIDATE_BOOLEAN);

$appNameScrenn = getenv('APP_NAME_SCREEN') ?: 'WallPanel';


// ========================================================================
// Sanitización estricta de columnas
// ========================================================================
$allowedPrices = ['precio1', 'precio2', 'precio3', 'precio4', 'preciodetalle', 'preciodetalle2'];
if (!in_array($appPriceTier, $allowedPrices)) $appPriceTier = 'precio1';
if (!in_array($appRetailPriceTier, $allowedPrices)) $appRetailPriceTier = 'preciodetalle';

// ========================================================================
// 2. INICIO DE LA APLICACIÓN (Lógica Nativa)
// ========================================================================
$api = $_GET['api'] ?? false;
$route = $_GET['route'] ?? false;

class Database
{
    private $conn;
    public function __construct($host, $user, $pass, $name)
    {
        $this->conn = new mysqli($host, $user, $pass, $name);
        if ($this->conn->connect_error) {
            responseError("Error de conexión a la base de datos.");
            exit;
        }
        $this->conn->set_charset("utf8mb4");
    }
    public function query($sql)
    {
        return $this->conn->query($sql);
    }
    public function escape($string)
    {
        return $this->conn->real_escape_string($string);
    }
}

function responseOk($data)
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => false, 'message' => 'Success', 'data' => $data]);
}
function responseError($msg)
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => $msg, 'data' => null]);
}

// 3. RUTAS DE API (BACKEND)
if ($api === 'true') {
    try {
        if ($route === 'getProduct') {
            $barcode = $_GET['barcode'] ?? null;
            if (!$barcode) throw new Exception("Código de barras vacío");

            $db = new Database($dbHost, $dbUser, $dbPass, $dbName);
            $barcode_safe = $db->escape(trim($barcode));



            //Actualizar este sql y parametros para adaptar al sistema
            $sql = "SELECT p.codigo1 AS barcode, 
               p.descripcion1 AS name, 
               p.impuesto AS tax_percent, 
               ps.{$appPriceTier} AS main_price,
               ps.{$appRetailPriceTier} AS retail_price
        FROM productos p
        INNER JOIN productos_suc ps ON p.codigo1 = ps.codigo1
        WHERE '$barcode_safe' IN (p.codigo1, p.codigo2, p.codigo3, p.codigo4) 
        AND ps.sucursal = $appBranch 
        LIMIT 1";



            $queryProduct = $db->query($sql);
            if (!$queryProduct) throw new Exception("Error al consultar el producto");

            $data = $queryProduct->fetch_assoc();

            if (!$data) {
                throw new Exception("Producto no encontrado");
            }

            // Casting de valores
            $taxPercent = floatval($data['tax_percent']);
            $mainPrice = floatval($data['main_price']);
            $retailPrice = floatval($data['retail_price']);

            // Lógica de Impuesto
            $taxApplied = false;
            if ($appApplyTax && $taxPercent > 0) {
                $mainPrice += $mainPrice * ($taxPercent / 100);
                $retailPrice += $retailPrice * ($taxPercent / 100);
                $taxApplied = true;
            }

            // Lógica de Redondeo
            if ($appRoundPrice) {
                $mainPrice = round($mainPrice);
                $retailPrice = round($retailPrice);
            }

            responseOk([
                'product' => [
                    'barcode' => $data['barcode'],
                    'name' => trim($data['name']),
                    'price' => $mainPrice,
                    'retail_price' => $retailPrice
                ],
                'config' => [
                    'currency' => $appCurrency,
                    'show_retail' => $appShowRetailPrice,
                    'tax_applied' => $taxApplied,
                    'tax_percent' => $taxPercent,
                    'round_price' => $appRoundPrice
                ]
            ]);
        } else {
            throw new Exception("Ruta desconocida: $route");
        }
    } catch (Exception $e) {
        responseError($e->getMessage());
    }
    return;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $appNameScrenn; ?></title>

    <script src="./onscan.min.js"></script>

    <style>
        /* RESET & VARIABLES GLOBALES */
        * {
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            user-select: none;
        }

        :root {
            --primary: #04b19c;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --bg-color: #ffffff;
            --bg-alt: #f8fafc;
            --border: #e2e8f0;
            --error: #ef4444;
            --font-stack: system-ui, -apple-system, sans-serif;
        }

        body,
        html {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: var(--font-stack);
            background-color: var(--bg-color);
            color: var(--text-main);
            overflow: hidden;
        }

        .watermark {
            position: absolute;
            bottom: 2vh;
            right: 2vw;
            font-size: clamp(2rem, 8vw, 6rem);
            font-weight: 900;
            color: rgba(0, 0, 0, 0.03);
            z-index: 0;
            letter-spacing: -2px;
        }

        .app-container {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            box-sizing: border-box;
            z-index: 10;
        }

        /* ANIMACIONES NATIVAS */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.98);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .view-section {
            display: none;
            /* Controlado por JS */
            animation: fadeIn 0.3s ease-out forwards;
        }

        .status-view {
            text-align: center;
        }

        .title-main {
            font-size: clamp(2rem, 5vmin, 4rem);
            font-weight: 800;
            margin: 1rem 0 0.5rem;
        }

        .subtitle {
            font-size: clamp(1rem, 2.5vmin, 1.5rem);
            color: var(--text-muted);
            margin: 0;
        }

        .barcode-icon svg {
            width: clamp(80px, 15vmin, 150px);
            height: clamp(80px, 15vmin, 150px);
            fill: var(--border);
            margin-bottom: 1rem;
        }

        .loader {
            border: 6px solid var(--bg-alt);
            border-top: 6px solid var(--primary);
            border-radius: 50%;
            width: 80px;
            height: 80px;
            animation: spin 1s linear infinite;
            margin: 0 auto 2rem;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .btn {
            background: var(--text-main);
            color: #fff;
            border: none;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn:hover {
            background: #000;
        }

        /* PRODUCTO PRINCIPAL */
        .product-view {
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            width: 100%;
            height: 100%;
            gap: 2vh;
        }

        .product-price {
            font-size: clamp(4rem, 16vmin, 12rem);
            font-weight: 900;
            color: var(--primary);
            line-height: 1;
            letter-spacing: -0.04em;
            margin: 0;
            text-shadow: 0 10px 30px rgba(39, 180, 133, 0.15);
        }

        /* PRECIO AL DETALLE (SECUNDARIO) ESTILO CLÁSICO */
        .product-retail-container {
            margin-top: -1vh;
            margin-bottom: 2vh;
            display: none;
            /* Controlado por JS */
        }

        .retail-label {
            font-size: clamp(1rem, 3vmin, 1.5rem);
            font-weight: 600;
            color: var(--text-muted);
        }

        .product-retail-price {
            font-size: clamp(1.5rem, 4vmin, 2.5rem);
            font-weight: 800;
            color: var(--text-main);
            margin-left: 8px;
        }

        .product-name {
            font-size: clamp(1.5rem, 6vmin, 4rem);
            line-height: 1.2;
            font-weight: 800;
            margin: 0;
            color: var(--text-main);
            max-width: 90%;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .offline-notification {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            background-color: var(--error);
            color: #fff;
            padding: 12px;
            text-align: center;
            font-weight: bold;
            font-size: 1rem;
            z-index: 1000;
            display: none;
            /* Controlado por JS */
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            animation: fadeIn 0.3s ease-out forwards;
        }
    </style>
</head>

<body>

    <div class="watermark"><?php echo $appNameScrenn; ?></div>

    <div class="app-container">
        <div id="offline-notification" class="offline-notification">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2.5" fill="none">
                <line x1="1" y1="1" x2="23" y2="23"></line>
                <path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"></path>
                <path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"></path>
                <path d="M10.71 5.05A16 16 0 0 1 22.58 9"></path>
                <path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"></path>
                <path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path>
                <line x1="12" y1="20" x2="12.01" y2="20"></line>
            </svg>
            Sin conexión a la red. Por favor, verifica tu internet.
        </div>

        <div id="view-waiting" class="status-view view-section" style="display: block;">
            <div class="barcode-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M3 5h2v14H3V5zm4 0h1v14H7V5zm3 0h2v14h-2V5zm4 0h1v14h-1V5zm3 0h2v14h-2V5zm4 0h1v14h-1V5zm4 0h1v14h-1V5z" />
                </svg>
            </div>
            <h1 class="title-main">Escanea un producto</h1>
            <p class="subtitle">El precio aparecerá al instante</p>
        </div>

        <div id="view-loading" class="status-view view-section">
            <div class="loader"></div>
        </div>

        <div id="view-error" class="status-view view-section">
            <div class="barcode-icon" style="fill: var(--error);">
                <svg viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z" />
                </svg>
            </div>
            <h1 class="title-main">No encontrado</h1>
            <p id="error-barcode" style="font-family: monospace; font-size: 1.5rem; color: var(--text-muted); background: var(--bg-alt); padding: 0.5rem 1rem; border-radius: 8px; display: inline-block;"></p>
            <div style="margin-top: 2rem;">
                <button id="btn-reset" class="btn">Limpiar Pantalla</button>
            </div>
        </div>

        <div id="view-product" class="product-view view-section">
            <div id="product-price" class="product-price"></div>

            <div id="product-retail-container" class="product-retail-container">
                <span class="retail-label">Precio al detalle:</span>
                <span id="product-retail-price" class="product-retail-price"></span>
            </div>

            <h2 id="product-name" class="product-name"></h2>
        </div>
    </div>

    <script>
        // =========================================================
        // LÓGICA DE LA APLICACIÓN (VANILLA JAVASCRIPT)
        // =========================================================
        const API_URL = window.location.href.split('?')[0];
        let resetTimeout = null;
        let configData = {
            currency: 'RD$',
            show_retail: false,
            tax_applied: false,
            tax_percent: 0,
            round_price: false
        };

        // Referencias al DOM
        const ui = {
            offline: document.getElementById('offline-notification'),
            views: {
                waiting: document.getElementById('view-waiting'),
                loading: document.getElementById('view-loading'),
                error: document.getElementById('view-error'),
                product: document.getElementById('view-product')
            },
            product: {
                price: document.getElementById('product-price'),
                name: document.getElementById('product-name'),
                retailContainer: document.getElementById('product-retail-container'),
                retailPrice: document.getElementById('product-retail-price')
            },
            errorBarcode: document.getElementById('error-barcode'),
            btnReset: document.getElementById('btn-reset')
        };

        // Utilidades de la UI
        function switchView(viewName) {
            // Ocultar todas las vistas
            Object.values(ui.views).forEach(el => el.style.display = 'none');

            // Mostrar la vista objetivo (usando display correcto para la animación CSS)
            if (viewName === 'product') {
                ui.views[viewName].style.display = 'flex';
            } else {
                ui.views[viewName].style.display = 'block';
            }
        }

        function updateOnlineStatus() {
            ui.offline.style.display = navigator.onLine ? 'none' : 'flex';
        }

        function resetScanner() {
            switchView('waiting');
            ui.errorBarcode.textContent = '';
            if (resetTimeout) clearTimeout(resetTimeout);
        }

        function formatMoney(val) {
            const numVal = parseFloat(val);
            // Decide si formatea con decimales o como número entero según la configuración
            const fractionDigits = configData.round_price ? 0 : 2;

            const numStr = numVal.toLocaleString('en-US', {
                minimumFractionDigits: fractionDigits,
                maximumFractionDigits: fractionDigits
            });
            return `${configData.currency} ${numStr}`;
        }

        // Lógica de Escaneo
        async function handleScan(code) {
            if (!code) return;

            updateOnlineStatus();

            if (!navigator.onLine) {
                ui.errorBarcode.textContent = "SIN CONEXIÓN";
                switchView('error');
                if (resetTimeout) clearTimeout(resetTimeout);
                resetTimeout = setTimeout(resetScanner, 4000);
                return;
            }

            ui.errorBarcode.textContent = code;
            switchView('loading');
            if (resetTimeout) clearTimeout(resetTimeout);

            try {
                const response = await fetch(`${API_URL}?api=true&route=getProduct&barcode=${encodeURIComponent(code)}`);
                const json = await response.json();

                if (json.error) throw new Error(json.message);

                const product = json.data.product;
                configData = json.data.config;

                // Llenar datos en el DOM
                ui.product.price.textContent = formatMoney(product.price);
                ui.product.name.textContent = product.name;

                // Lógica visual del Precio al Detalle
                if (configData.show_retail && product.price !== product.retail_price) {
                    ui.product.retailPrice.textContent = formatMoney(product.retail_price);
                    ui.product.retailContainer.style.display = 'block';
                } else {
                    ui.product.retailContainer.style.display = 'none';
                }

                switchView('product');

                // Auto-limpiar pantalla en 15 segundos
                resetTimeout = setTimeout(resetScanner, 15000);

            } catch (err) {
                switchView('error');
                resetTimeout = setTimeout(resetScanner, 4000);
            }
        }

        // Listeners y Eventos
        ui.btnReset.addEventListener('click', resetScanner);
        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);

        document.addEventListener('scan', (e) => {
            handleScan(e.detail.scanCode);
        });

        // Inicialización del Lector de Código de Barras (onScan.js)
        document.addEventListener("DOMContentLoaded", () => {
            updateOnlineStatus();
            onScan.attachTo(document, {
                avgTimeByChar: 80,
                minLength: 3,
                timeBeforeScanTest: 200,
                reactToKeyDown: true,
                reactToPaste: true
            });
        });

        // =========================================================
        // BLOQUEO DE SEGURIDAD (ANTI-INSPECCIÓN)
        // =========================================================
        document.addEventListener('contextmenu', event => event.preventDefault());
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F12' || e.keyCode === 123) e.preventDefault();
            if (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'i' || e.key === 'J' || e.key === 'j' || e.key === 'C' || e.key === 'c')) e.preventDefault();
            if (e.ctrlKey && (e.key === 'U' || e.key === 'u')) e.preventDefault();
        });
    </script>
</body>

</html>