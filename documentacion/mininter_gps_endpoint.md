# Documentación de Endpoints GPS – MININTER

> **Resumen rápido**  
> El SIPCOP‑M expone dos servicios REST sobre HTTPS/TLS 1.2:  
> * **`/retransmisionGPS/ubicacionGPS`** – recibe posiciones de vehículos de Serenazgo.  
> * **`/retransmisionpolicial/ubicacion/gps-policial`** – recibe posiciones de patrulleros policiales.  
> Ambos aceptan **POST** con JSON estrictamente tipado, devuelven normalmente **201 Created**, aplican lista blanca de IP y límites de carga (máx. 25 puntos por trama, 10 000 puntos por vehículo y día).

---

## 1 · Contexto y alcance
El **Sistema de Control de Patrullaje Municipal (SIPCOP‑M)** consolida la telemetría de vehículos municipales y policiales para medir el cumplimiento del *Compromiso 5* del Programa de Incentivos del MEF.  
La transmisión GPS se rige por el **Protocolo PT‑GPS v1.2** publicado por la OGTIC‑MININTER.

---

## 2 · Seguridad y prerequisitos

| Aspecto | Requisito |
|---------|-----------|
| Transporte | HTTPS (TLS 1.2 o superior) obligatorio. |
| Autenticación | Lista blanca de IP públicas registrada ante OGTIC. |
| Frecuencia | Vehículo **en marcha**: 1 punto cada 10 s.<br>Vehículo **apagado**: 1 punto cada 180 s. |
| Carga por trama | Máx. 25 puntos GPS por solicitud (5 s entre tramas). |
| Límite diario | 10 000 puntos por vehículo (excedentes se descartan). |
| Horario sugerido para lotes | L‑V después de 18:00 h; fines de semana sin restricción. |

---

## 3 · Endpoint *Serenazgo*

### 3.1 URL y método
```http
POST https://transmision.mininter.gob.pe/retransmisionGPS/ubicacionGPS
Content-Type: application/json
Accept: */*
```

### 3.2 Cuerpo de la solicitud
```jsonc
{
  "alarma": "string",
  "altitud": 0,
  "angulo": 0,
  "distancia": 0,
  "fechaHora": "dd/MM/yyyy HH:mm:ss",
  "horasMotor": 0,
  "idMunicipalidad": "string",
  "ignition": true,
  "imei": "string",
  "latitud": 0,
  "longitud": 0,
  "motion": true,
  "placa": "string",
  "totalDistancia": 0,
  "totalHorasMotor": 0,
  "ubigeo": "string",
  "valid": true,
  "velocidad": 0
}
```

### 3.3 Respuesta típica

| Código | Descripción | Acción recomendada |
|--------|-------------|--------------------|
| **201 Created** | Punto(s) almacenado(s). | Registrar y continuar. |
| 400 Bad Request | JSON mal formado o faltan campos. | Corregir y reintentar. |
| 401 Unauthorized | IP fuera de lista blanca o TLS inválido. | Revisar configuración. |
| 500 Internal Error | Falla temporal del servicio. | Reintentar tras 5 s. |

### 3.4 Restricciones específicas
* Latitud ±90°, Longitud ±180°.  
* `fechaHora` en hora local GMT‑5 con formato exacto `dd/MM/yyyy HH:mm:ss`.  
* El vehículo debe existir y estar habilitado con la bandera **Participa PI**.

---

## 4 · Endpoint *Policial*

### 4.1 URL y método
```http
POST https://transmision.mininter.gob.pe/retransmisionpolicial/ubicacion/gps-policial
Content-Type: application/json
Accept: */*
```

### 4.2 Cuerpo de la solicitud
```jsonc
{
  "alarma": "string",
  "altitud": 0,
  "angulo": 0,
  "codigoComisaria": "string",
  "distancia": 0,
  "fechaHora": "dd/MM/yyyy HH:mm:ss",
  "horasMotor": 0,
  "idTransmision": "string",
  "ignition": true,
  "imei": "string",
  "latitud": 0,
  "longitud": 0,
  "motion": true,
  "placa": "string",
  "totalDistancia": 0,
  "totalHorasMotor": 0,
  "ubigeo": "string",
  "valid": true,
  "velocidad": 0
}
```
**Diferencias clave**: se usan `codigoComisaria` e `idTransmision` en lugar de `idMunicipalidad`.

### 4.3 Respuesta y errores
Los códigos y acciones son idénticos a los del endpoint municipal.

---

## 5 · Buenas prácticas de operación
1. **Validación local** – use JSON Schema, verifique rangos y formatos antes de enviar.  
2. **Trazabilidad** – registre fecha/hora, payload, endpoint y código HTTP.  
3. **Retransmisiones** – reenvíe solo puntos faltantes basándose en el CSV oficial.  
4. **Ventanas de menor carga** – para lotes históricos, use los horarios recomendados.  
5. **Compatibilidad PI 2024‑2025** – el indicador 5.3 depende de la correcta transmisión.

---

## 6 · Tablas de referencia

### 6.1 Esquema *Serenazgo*

| Campo | Tipo | Longitud | Obligatorio | Descripción |
|-------|------|----------|-------------|-------------|
| idMunicipalidad | string | 10‑36 | Sí | Código único DGSC |
| imei | string | 15 | Sí | IMEI homologado MTC |
| fechaHora | string | 19 | Sí | `dd/MM/yyyy HH:mm:ss` GMT‑5 |
| latitud / longitud | double | — | Sí | Coordenadas WGS‑84 |
| altitud | double | — | Sí | Metros s.n.m. |
| … | … | … | … | Ver estructura completa arriba |

### 6.2 Esquema *Policial*
Idéntico al anterior salvo `idTransmision` y `codigoComisaria`.

---

## 7 · Códigos de estado resumidos

| HTTP | Motivo | ¿Reintentar? |
|------|--------|--------------|
| 201 | Registro exitoso | No |
| 400 | JSON inválido o faltan campos | Corregir y reintentar |
| 401 | IP no autorizada / TLS obsoleto | Corregir configuración |
| 500 | Error interno temporal | Sí, con back‑off de 5 s |

---

## 8 · Glosario

* **SIPCOP‑M** – Sistema de Control de Patrullaje Municipal.  
* **DGSC** – Dirección General de Seguridad Ciudadana.  
* **OGTIC** – Oficina General de Tecnologías de la Información y Comunicaciones.  
* **Compromiso 5** – Meta del Programa de Incentivos del MEF relacionada al patrullaje.  
* **TLS 1.2** – Capa de cifrado mínima aceptada por los servicios del MININTER.  
* **Trama** – Objeto JSON con hasta 25 puntos GPS.  
* **Ubigeo** – Código INEI de 6 dígitos que identifica distrito.

---

## 9 · Ejemplos de cURL

### 9.1 Serenazgo
```bash
curl -X POST   -H "Content-Type: application/json"   -d @serenazgo.json   "https://transmision.mininter.gob.pe/retransmisionGPS/ubicacionGPS"


curl -X POST "https://transmision.mininter.gob.pe/retransmisionGPS/ubicacionGPS" -H "accept: */*" -H "Content-Type: application/json" -d "{ \"alarma\": \"string\", \"altitud\": 0, \"angulo\": 0, \"distancia\": 0, \"fechaHora\": \"dd/MM/yyyy HH:mm:ss\", \"horasMotor\": 0, \"idMunicipalidad\": \"string\", \"ignition\": true, \"imei\": \"string\", \"latitud\": 0, \"longitud\": 0, \"motion\": true, \"placa\": \"string\", \"totalDistancia\": 0, \"totalHorasMotor\": 0, \"ubigeo\": \"string\", \"valid\": true, \"velocidad\": 0}"

```

### 9.2 Policial
```bash
curl -X POST   -H "Content-Type: application/json"   -d @policial.json   "https://transmision.mininter.gob.pe/retransmisionpolicial/ubicacion/gps-policial"
`

curl -X POST "https://transmision.mininter.gob.pe/retransmisionpolicial/ubicacion/gps-policial" -H "accept: */*" -H "Content-Type: application/json" -d "{ \"alarma\": \"string\", \"altitud\": 0, \"angulo\": 0, \"codigoComisaria\": \"string\", \"distancia\": 0, \"fechaHora\": \"dd/MM/yyyy HH:mm:ss\", \"horasMotor\": 0, \"idTransmision\": \"string\", \"ignition\": true, \"imei\": \"string\", \"latitud\": 0, \"longitud\": 0, \"motion\": true, \"placa\": \"string\", \"totalDistancia\": 0, \"totalHorasMotor\": 0, \"ubigeo\": \"string\", \"valid\": true, \"velocidad\": 0}"

> Reemplace `@*.json` por los archivos construidos siguiendo los esquemas anteriores.