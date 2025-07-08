# Documento de Especificaciones Técnicas
**Gestión y Reenvío Automático de Posiciones GPS de Municipalidades al MININTER**

---

## 1. Propósito
Diseñar e implementar una plataforma que:

1. **Consuma** posiciones de vehículos patrulla desde _GPServer_ mediante el **token GPS** asignado a cada municipalidad.  
2. **Transforme** la información al formato exigido por los endpoints del **MININTER** (tipos **SERENAZGO** y **POLICIAL**).  
3. **Envíe** los datos al MININTER en intervalos regulares (≈ 1 minuto) con alta disponibilidad, trazabilidad y capacidad de ampliación.  
4. **Administre** la lista de municipalidades y sus credenciales desde una interfaz gráfica (FilamentPHP 3), sin necesidad de editar código.

---

## 2. Datos Maestros Iniciales

| tipo       | municipalidad                       | token_gps (origen)                  | ubigeo | id_municipalidad (destino)           |
|------------|-------------------------------------|-------------------------------------|--------|--------------------------------------|
| SERENAZGO  | MUNICIPALIDAD DE HUARAZ             | 6B7E791FE9B05E845825B0F232AD65FC    | 020101 | 645d2bfa-2e02-4f0c-95a9-2032e9e2a941 |
| SERENAZGO  | MUNICIPALIDAD DE ACOLLA            | 9F5605BF8B91E838D7AE8A3DE526913E    | 120402 | 2ac6963e-8ac1-4444-b709-fa7d1ab0f1eb |
| SERENAZGO  | MUNICIPALIDAD DE APATA             | 6CAAD9D386D3AE0F6D395E13783B5239    | 120403 | 2b87c3ec-3a19-419a-ae63-ccb0ce8c9014 |
| SERENAZGO  | MUNICIPALIDAD DE HUAMANCACA        | 62827EC667E36A94D8E425016CDD59EA    | 120905 | 5ab398b4-a6e7-455a-a691-153f6ce78231 |
| SERENAZGO  | MUNICIPALIDAD GREGORIO GALBARRACÍN | D6DA254BA78A69E5F25086DF9695DF73    | 230110 | fabc13e7-cd3b-48d5-821b-5ba9ac3267ab |
| SERENAZGO  | MUNICIPALIDAD SAMEGUA              | B9F0726BBCBF5A871CB8A9770E987178    | 180104 | 9b6e814b-1661-4bee-a0ca-d331db1c1d25 |
| SERENAZGO  | MUNICIPALIDAD CIUDAD NUEVA         | 94AE8A0B24264D1C9F3B666C88823A80    | 230104 | 69e69897-52ea-4310-940e-cea95fa725e5 |
| SERENAZGO  | MUNICIPALIDAD DE TACNA             | 5DF4031AC4061139BDDFD120084C516C    | 230101 | 99ec8a66-df6b-41ba-a96f-ad13390a4a25 |
| POLICIAL   | MUNICIPALIDAD GREGORIO GALBARRACÍN | 6DD3BE213236B049F03EA7C35EBD2257    | 230110 | fabc13e7-cd3b-48d5-821b-5ba9ac3267ab |
| POLICIAL   | MUNICIPALIDAD DE TACNA             | 08D001253591DB820DD46FFF4F901E51    | 230101 | 99ec8a66-df6b-41ba-a96f-ad13390a4a25 |

> Esta tabla se cargará en la base de datos mediante **migration + seeder**; cada fila representa una combinación _municipalidad + tipo_.

---

## 3. Modelo de Datos (DB)

| Tabla | Campos clave | Observaciones |
|-------|--------------|---------------|
| **municipalities** | `id` (uuid PK), `name`, `token_gps`, `ubigeo`, `tipo` (`SERENAZGO` / `POLICIAL`), `codigo_comisaria` (nullable, solo policial), `active`, timestamps | Origen y destino de credenciales |
| **transmissions**  | `id` (uuid PK), `municipality_id` (FK), `payload`, `response_code`, `status` (`SENT` / `FAILED`), `sent_at`, `retry_count` | Historial de envíos |
| **failed_jobs** | estándar de Laravel Queue | Reintentos automáticos |

---

## 4. Flujo de Procesamiento

```
Scheduler (cada 1 min)
 └─► SyncMunicipalityJob (cola: sync)
       ├─► GpServerClient::fetch(token_gps)
       ├─► PayloadTransformer::transform()
       ├─► MininterClient::send()
       └─► Persistir transmisión + reintentos
```

Los workers se ejecutan de forma continua mediante **systemd/Supervisor** o **Laravel Horizon**.

---

## 5. Stack Tecnológico

| Capa | Herramienta | Justificación |
|------|-------------|---------------|
| Backend | **Laravel 12** | Framework robusto, soporte para Octane, colas, testing y seguridad. |
| Concurrencia | **Redis 6** + Laravel Queue + **Horizon** | Despacho y monitoreo de trabajos asíncronos. |
| Front‑end admin | **FilamentPHP 3** | CRUD visual sin código para municipalidades y métricas. |
| Worker persistente | **systemd** / Supervisor | Reinicio automático en fallas. |
| Opcional alto rendimiento | **Laravel Octane** | Reduce latencia si la frecuencia baja a < 30 s. |
| Observabilidad | Grafana + Loki / Prometheus | Logs, métricas y alertas en tiempo real. |

---

## 6. Especificaciones de Endpoints

| Rol | Proveedor | URL base (ejemplo) | Autenticación |
|-----|-----------|--------------------|---------------|
| **Origen** | GPServer | `https://gpserver.example.com/api/positions?token={TOKEN}` | Token por URL |
| **Destino (SERENAZGO)** | MININTER | `https://mininter.gob.pe/api/serenazgo/posiciones` | API‑Key header |
| **Destino (POLICIAL)** | MININTER | `https://mininter.gob.pe/api/policial/posiciones` | API‑Key header |

---

## 7. Esquemas de Datos

### 7.1 Entrada GPServer (común)

| Campo | Tipo | Ejemplo |
|-------|------|---------|
| alarma | string | "" |
| altitud | int | 2793 |
| angulo | int | 314 |
| distancia | int | 68485 |
| fechaHora | string `dd/MM/yyyy HH:mm:ss` | "12/12/2020 07:42:50" |
| horasMotor | int | 14 |
| imei | string | "359632109283942" |
| latitud | float | -7.1626 |
| longitud | float | -78.5189 |
| motion | bool | true |
| totalDistancia | int | 15 |
| totalHorasMotor | int | 0 |
| ubigeo | string(6) | "060101" |
| placa | string | "XXX-123" |
| valid | bool | true |
| velocidad | int | 17 |

### 7.2 Salida MININTER

| Campo adicional | SERENAZGO | POLICIAL | Fuente |
|-----------------|-----------|----------|--------|
| idMunicipalidad | ✔ | — | `municipalities.id` |
| idTransmision   | — | ✔ | UUID generado |
| codigoComisaria | — | ✔ | `municipalities.codigo_comisaria` |

Todos los demás campos se copian 1:1 desde GPServer.

---

## 8. Ejemplos de Payload

### 8.1 SERENAZGO

```json
{
  "alarma": "",
  "altitud": 2793,
  "angulo": 314,
  "distancia": 68485,
  "fechaHora": "12/12/2020 07:42:50",
  "horasMotor": 14,
  "idMunicipalidad": "495268a2-acc0-45a8-bf7f-20dc61c64",
  "ignition": true,
  "imei": "359632109283942",
  "latitud": -7.1626,
  "longitud": -78.5189,
  "motion": true,
  "totalDistancia": 15,
  "totalHorasMotor": 0,
  "ubigeo": "060101",
  "placa": "XXX-123",
  "valid": true,
  "velocidad": 17
}
```

### 8.2 POLICIAL  
_Diferencia: incluye **codigoComisaria** y **idTransmision**._

```json
{
  "alarma": "string",
  "altitud": 11,
  "angulo": 12,
  "codigoComisaria": "150101",
  "distancia": 13,
  "fechaHora": "03/01/2024 17:55:00",
  "horasMotor": 14,
  "idTransmision": "8f4b46f9-7dee-4e2d-aad2-65e9b650ec41",
  "ignition": true,
  "imei": "344334433420000",
  "latitud": 10.04441,
  "longitud": -20.4402,
  "motion": true,
  "placa": "XXX-123",
  "totalDistancia": 15,
  "totalHorasMotor": 16,
  "ubigeo": "150118",
  "valid": true,
  "velocidad": 17
}
```

---

## 9. Lógica de Transformación

1. **Filtrar** los campos listados en 7.1.  
2. **Añadir**:  
   * _SERENAZGO_: `idMunicipalidad` ← `municipalities.id`.  
   * _POLICIAL_: `idTransmision` ← UUID; `codigoComisaria` ← `municipalities.codigo_comisaria` o `ubigeo`.  
3. **Validar** rangos y formatos.  
4. **Serializar** como `application/json; charset=utf-8`.

---

## 10. Políticas de Reintento

| Parámetro | Valor |
|-----------|-------|
| Máx intentos | 5 |
| Backoff | 60 s → 120 s → 300 s → 600 s → 1800 s |
| Corte de circuito | `failed_jobs` > N en 10 min → alerta + pausa 5 min |
| Idempotencia | `idTransmision` UUID evita duplicados |

---

## 11. Seguridad

* Tokens y API‑Keys en variables **.env**.  
* TLS 1.2+ en todos los endpoints.  
* Rate‑limit local para respetar cuotas MININTER.  
* Rotación de secretos via Laravel Vault / AWS KMS.

---

## 12. Interfaz Administrativa

| Elemento | Detalle |
|----------|---------|
| **MunicipalityResource** | Alta/Baja, edición de `token_gps`, `ubigeo`, `tipo`, `codigo_comisaria`, `active`. |
| **Dashboard** | Gráficos de éxitos/fallos, últimos errores, estado workers. |
| **Log viewer** | Filtro por municipalidad y rango de fechas. |

---

## 13. Plan de Pruebas

1. **Unitarias**: verificar transformador (SERENAZGO/POLICIAL).  
2. **Integración**: mock GPServer y MININTER sandbox.  
3. **Carga**: 6 000 req/h (100 municipios).  
4. **Recuperación**: simular 503 MININTER y validar reintentos.

---

## 14. Despliegue

1. `git pull && composer install --optimize-autoloader`.  
2. `php artisan migrate --seed`.  
3. Configurar Redis y lanzar Horizon.  
4. Registrar servicios systemd (`queue:work`, `schedule:work`).  
5. Configurar Grafana/Loki.  
6. Activar alertas (Slack/Email).

---

## 15. Escalabilidad

* Colas separadas por tipo (`sync:serenazgo`, `sync:policial`).  
* Autoescalado de workers (Kubernetes HPA).  
* Cache de posiciones estáticas (> N min sin movimiento).  
* Circuit Breaker para indisponibilidad prolongada MININTER.

---

## 16. Mantenimiento

| Área | Responsable | KPI |
|------|-------------|-----|
| Infra & Workers | DevOps | Uptime > 99.5 % |
| Transformador/API | Backend | Éxito ≥ 98 % |
| Datos maestros | Admin | Alta < 24 h |
| Monitoreo | SRE | MTTR < 30 min |

---

**Fin del documento**