
# Guía rápida de consumo – API `USER_GET_OBJECTS` · GIPIES GPS‑Server

Esta guía explica **cómo consultar la lista de dispositivos** asociados a tu cuenta en **GIPIES** (plataforma basada en _GPS‑Server.net_) mediante el comando `USER_GET_OBJECTS`.  
Incluye ejemplo real de respuesta y tabla de campos para que cualquier sistema (o IA) pueda indexar y procesar la información.

---

## 1 · URL base y autenticación

```
https://www.gipies.pe/api/api.php
```

### Parámetros obligatorios

| Parámetro | Valor | Descripción |
|-----------|-------|-------------|
| `api` | `user` | Indica que se usa la API de usuario. |
| `key` | **tu API key** | Cadena única asociada a tu cuenta.<br>• Se obtiene en **CPanel → Account → API key**.<br>• Si no la conoces, puedes recuperarla usando usuario/contraseña: `api=user&username=USR&password=PWD`. |
| `cmd` | `USER_GET_OBJECTS` | Comando que solicita la lista de objetos GPS. |

> **Método HTTP:** `GET`  
> **Autenticación adicional:** no requiere encabezados; la clave va como parámetro.

---

## 2 · Ejemplo de petición

```bash
curl "https://www.gipies.pe/api/api.php?api=user&key=6B7E791FE9B05E845825B0F232AD65FC&cmd=USER_GET_OBJECTS"
```

---

## 3 · Respuesta (JSON)

La API devuelve un **array de objetos**; cada elemento corresponde a un dispositivo GPS.  
Ejemplo abreviado (un solo elemento):

```json
{
  "imei": "352016709797336",
  "name": "EAI-994",
  "plate_number": "EAI-994",
  "protocol": "teltonikafm",
  "net_protocol": "tcp",
  "ip": "161.0.161.50",
  "port": "11922",
  "lat": "-9.529452",
  "lng": "-77.529653",
  "altitude": "3080",
  "angle": "295",
  "speed": "0",
  "loc_valid": "1",
  "dt_server": "2025-07-08 05:34:14",
  "dt_tracker": "2025-07-08 05:34:09",
  "odometer": "34382.355165",
  "engine_hours": "0",
  "params": {},
  "custom_fields": [
    { "name": "idTransmision", "value": "645d2bfa-2e02-4f0c-95a9-2032e9e2a941" },
    { "name": "ubigeo",        "value": "020101" }
  ]
}
```

---

## 4 · Esquema de campos relevantes

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `imei` | string | Identificador único de 15 dígitos del dispositivo GPS. |
| `name` | string | Alias amigable del vehículo. |
| `plate_number` | string | Número de placa vehicular. |
| `lat` | string | Latitud en grados decimales (WGS‑84). |
| `lng` | string | Longitud en grados decimales (WGS‑84). |
| `altitude` | string | Altitud en metros sobre el nivel del mar. |
| `speed` | string | Velocidad en km/h. |
| `angle` | string | Ángulo de rumbo (0‑359°). |
| `odometer` | string | Odómetro acumulado en km. |
| `engine_hours` | string | Horas totales de motor. |
| `dt_server` | string | Fecha/hora de recepción por el servidor (GMT‑0). |
| `dt_tracker` | string | Fecha/hora generada por el tracker (GMT‑0). |
| `loc_valid` | string | 1 si la posición es válida, 0 si no. |
| `params` | string | Objeto con lecturas de sensores y parámetros adicionales. |
| `custom_fields` | string | Lista de pares nombre/valor configurables (ej. idTransmision, ubigeo). |

> **Notas**  
> * Los valores se devuelven como cadenas (`string`) incluso para números; convierte según tu necesidad.  
> * El horario de `dt_server` y `dt_tracker` está en **UTC 0**.<br>Si necesitas GMT‑5 aplica desplazamiento de –5 horas.  
> * El objeto `params` varía por modelo y puede incluir sensores (`hdop`, `gsmlev`, etc.).  
> * En `custom_fields` es común encontrar **`idTransmision`** (UUID de tu transmisión MININTER) y **`ubigeo`** (código de distrito).

---

## 5 · Manejo de errores

| HTTP | Posible causa | Recomendación |
|------|---------------|---------------|
| 200 OK | Datos devueltos con éxito. | Procesar normalmente. |
| 400 Bad Request | Falta `api`, `key` o `cmd`. | Verifica parámetros. |
| 401 Unauthorized | API key inválida o deshabilitada. | Regenera clave en CPanel. |
| 5xx | Falla temporal del servidor. | Reintentar con back‑off exponencial. |

> **Rate limit**: la documentación oficial no define un límite estricto, pero se aconseja máximo **1 petición por segundo** cuando se consulta `USER_GET_OBJECTS`.

---

## 6 · Buenas prácticas

1. **Cacheo corto** – Los datos cambian cada pocos segundos; si integras un panel refresca cada 30 s.  
2. **Filtrado** – Algunos objetos pueden estar inactivos (`active=false`) o con GPS inválido (`loc_valid=0`).  
3. **Normalización** – Convierte `lat`, `lng`, `speed`, `altitude` y `odometer` a valores numéricos antes de usarlos.  
4. **Trazabilidad** – Guarda `dt_server` para detectar retrasos en el envío del tracker.  
5. **Compatibilidad MININTER** – Asegura que `custom_fields` incluya `idTransmision` y `ubigeo` antes de retransmitir al endpoint del MININTER.

---

## 7 · Ejemplo de código (PHP 8 + cURL)

```php
<?php
$base = 'https://www.gipies.pe/api/api.php';
$key  = '6B7E791FE9B05E845825B0F232AD65FC';

$url = $base . '?api=user&key=' . $key . '&cmd=USER_GET_OBJECTS';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
]);

$response = curl_exec($ch);
curl_close($ch);

$objects = json_decode($response, true);
foreach ($objects as $obj) {
    echo $obj['imei'] . ' → ' . $obj['lat'] . ',' . $obj['lng'] . PHP_EOL;
}
?>
```

---

## 8 · Recursos adicionales

* Manual completo **GPS‑Server User API 1.20** (`gps-server.pdf`)  
* Parámetros de sensores (`params`): <https://docs.gps-server.net/top_panel/sensor_parameters.html>

---

Con esta guía en formato Markdown puedes integrar y procesar rápidamente la salida del endpoint `USER_GET_OBJECTS`.