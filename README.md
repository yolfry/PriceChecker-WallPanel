# Price Checker WallPanel 🏷️

Aplicación ultraligera para consulta de precios mediante escáner de código de barras. Linux WallPanel

---

## 🚀 Requisitos Previos

- Docker y Docker Compose instalados en el servidor o equipo host.
- Base de datos MySQL accesible desde el host (o configurada en la red de Docker).

---

## ⚙️ Variables de Entorno (Configuración)

Toda la configuración del sistema se maneja a través de un archivo oculto llamado `.env`.

**Antes de iniciar**, crea un archivo `.env` en la raíz del proyecto (puedes copiar estas variables y ajustar sus valores):

### 🌐 Red y Exposición

| Variable   | Descripción                                                  | Ejemplo      |
| ---------- | ------------------------------------------------------------ | ------------ |
| `APP_PORT` | Puerto en tu máquina (host) donde se podrá acceder a la app. | `8080`, `80` |

### 🗄️ Credenciales de Base de Datos

| Variable  | Descripción                                                                                                    | Ejemplo / Valores Permitidos             |
| --------- | -------------------------------------------------------------------------------------------------------------- | ---------------------------------------- |
| `DB_HOST` | Dirección del servidor MySQL. Si MySQL está instalado localmente en la máquina host, usa el valor por defecto. | `host.docker.internal` o `192.168.1.100` |
| `DB_USER` | Usuario de la base de datos con permisos de lectura.                                                           | `root`                                   |
| `DB_PASS` | Contraseña del usuario de la base de datos.                                                                    | `MiClaveSegura123`                       |
| `DB_NAME` | Nombre de la base de datos a consultar.                                                                        | `pricedb`                                |

### 🏢 Configuración General de la App

| Variable       | Descripción                                                                                              | Ejemplo / Valores Permitidos |
| -------------- | -------------------------------------------------------------------------------------------------------- | ---------------------------- |
| `APP_CURRENCY` | Símbolo de la moneda que acompañará los precios en pantalla. (Enciérralo en comillas).                   | `"RD$"`, `"€"`, `"US$"`      |
| `APP_BRANCH`   | Número identificador de la sucursal. Se usa para filtrar el precio correcto en la tabla `productos_suc`. | `1`, `2`, `3`                |

### 💰 Configuración de Precios Principales, Impuestos y Redondeo

| Variable          | Descripción                                                                                                                                  | Ejemplo / Valores Permitidos          |
| ----------------- | -------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------- |
| `APP_PRICE_TIER`  | Nombre de la columna en la tabla `productos_suc` que se utilizará como **Precio Principal** (el más grande en pantalla).                     | `precio1`, `precio2`, `preciodetalle` |
| `APP_APPLY_TAX`   | Define si el sistema debe extraer el porcentaje del campo `impuesto` de la base de datos y sumarlo automáticamente al precio final mostrado. | `true` o `false`                      |
| `APP_ROUND_PRICE` | Define si el precio final debe ser redondeado a números enteros (sin centavos/decimales).                                                    | `true` o `false`                      |

### 🛒 Configuración de Precio al Detalle (Secundario)

| Variable                | Descripción                                                                                                                                                                                      | Ejemplo / Valores Permitidos                 |
| ----------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------- |
| `APP_RETAIL_PRICE_TIER` | Nombre de la columna para el **Precio al Detalle** (precio secundario).                                                                                                                          | `preciodetalle`, `preciodetalle2`, `precio1` |
| `APP_SHOW_RETAIL_PRICE` | Muestra un indicador pequeño con el precio al detalle debajo del precio principal. **El sistema solo lo mostrará si está en `true` Y si el precio al detalle es diferente al precio principal.** | `true` o `false`                             |

---
