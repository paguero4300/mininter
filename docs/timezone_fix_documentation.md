# Corrección de Zona Horaria en PayloadTransformer

## Resumen del Problema

El sistema GPS proxy estaba enviando fechas a MININTER con una diferencia de 5 horas debido a que no se estaba realizando la conversión correcta de zona horaria entre los datos recibidos de GPServer y los datos enviados a MININTER.

## Análisis Técnico

### Estado Anterior
- **GPServer**: Enviaba datos GPS con campos `dt_server` y `dt_tracker` en **UTC (GMT-0)**
- **PayloadTransformer**: Tomaba la fecha y solo la formateaba sin conversión de zona horaria
- **MININTER**: Recibía las fechas en UTC cuando debería recibirlas en **GMT-5 (hora local de Perú)**

### Impacto del Problema
- Todas las fechas GPS enviadas a MININTER tenían 5 horas de diferencia respecto a la hora local de Perú
- Los datos de ubicación temporal no coincidían con la hora real de los eventos GPS

## Solución Implementada

### Cambios en `PayloadTransformer.php`

#### Método `formatDateTime()` (líneas 163-191)

**Antes:**
```php
private function formatDateTime($dateTime): string
{
    try {
        if (is_numeric($dateTime)) {
            $carbon = Carbon::createFromTimestamp((int) $dateTime);
        } else {
            $carbon = Carbon::parse($dateTime);
        }
        return $carbon->format('d/m/Y H:i:s');
    } catch (\Exception $e) {
        // ... manejo de errores
        return Carbon::now()->format('d/m/Y H:i:s');
    }
}
```

**Después:**
```php
private function formatDateTime($dateTime): string
{
    try {
        $targetTimezone = config('services.data_transformation.timezone', 'America/Lima');
        
        if (is_numeric($dateTime)) {
            // Timestamp Unix - crear desde timestamp en UTC
            $carbon = Carbon::createFromTimestamp((int) $dateTime, 'UTC');
        } else {
            // String de fecha - parsear como UTC (como viene de GPServer)
            $carbon = Carbon::parse($dateTime, 'UTC');
        }

        // Convertir de UTC a la zona horaria de destino (America/Lima = GMT-5)
        $carbon->setTimezone($targetTimezone);

        return $carbon->format('d/m/Y H:i:s');

    } catch (\Exception $e) {
        // ... manejo de errores
        $targetTimezone = config('services.data_transformation.timezone', 'America/Lima');
        return Carbon::now($targetTimezone)->format('d/m/Y H:i:s');
    }
}
```

### Cambios Clave

1. **Configuración Dinámica**: Se utiliza `config('services.data_transformation.timezone', 'America/Lima')` para obtener la zona horaria de destino desde la configuración

2. **Parseo en UTC**: Se especifica explícitamente que las fechas entrantes están en UTC tanto para timestamps como para strings de fecha

3. **Conversión de Zona Horaria**: Se utiliza `setTimezone()` para convertir de UTC a America/Lima antes del formateo

4. **Fallback Consistente**: El manejo de errores también respeta la zona horaria correcta

## Configuración Utilizada

### `config/services.php` (línea 89)
```php
'data_transformation' => [
    'timezone' => env('GPS_TIMEZONE', 'America/Lima'),
    // ... otras configuraciones
],
```

Esta configuración permite:
- Cambiar la zona horaria de destino via variable de entorno `GPS_TIMEZONE`
- Mantener 'America/Lima' como valor por defecto
- Flexibilidad para diferentes despliegues

## Verificación y Testing

### Tests Implementados

Se creó `tests/Unit/PayloadTransformerTimezoneTest.php` con los siguientes casos de prueba:

1. **Conversión básica UTC a Lima**: UTC 15:30:00 → Lima 10:30:00
2. **Timestamp como string**: Verificar que funciona con timestamps en formato string
3. **Fecha como string ISO**: Verificar que funciona con fechas en formato ISO
4. **Diferencia de 5 horas**: Confirmar que siempre hay exactamente 5 horas de diferencia
5. **Funcionalidad policial**: Verificar que funciona tanto para serenazgo como policial

### Tests Actualizados

Se actualizaron los tests existentes en `tests/Feature/Services/PayloadTransformerTest.php`:

- Corrección de nombres de campos (`lat` → `latitud`, `lng` → `longitud`, `rumbo` → `angulo`)
- Ajuste de expectativas de fechas para reflejar la conversión UTC→GMT-5
- Actualización de validaciones para usar los campos correctos del transformador

## Ejemplos de Conversión

### Ejemplo 1: Timestamp Unix
```php
// Entrada: 1705329025 (15/01/2024 14:30:25 UTC)
// Salida: "15/01/2024 09:30:25" (15/01/2024 09:30:25 GMT-5)
```

### Ejemplo 2: String de fecha
```php
// Entrada: "2024-01-15T20:45:30Z" (UTC)
// Salida: "15/01/2024 15:45:30" (GMT-5)
```

### Ejemplo 3: Fecha sin timezone
```php
// Entrada: "2024-01-15 12:00:00" (asumida como UTC)
// Salida: "15/01/2024 07:00:00" (GMT-5)
```

## Impacto en el Sistema

### Beneficios
- ✅ Fechas GPS ahora se envían correctamente a MININTER en GMT-5
- ✅ Datos temporales coinciden con la hora local de Perú
- ✅ Configuración flexible via archivo de configuración
- ✅ Manejo de errores mejorado con zona horaria correcta

### Consideraciones
- ⚠️ **Datos históricos**: Las fechas enviadas antes de esta corrección tenían 5 horas de diferencia
- ⚠️ **Compatibilidad**: Los sistemas que consumen datos de MININTER deben estar preparados para la hora local correcta
- ⚠️ **Monitoreo**: Se recomienda monitorear los logs para verificar que no hay errores de conversión

## Validación en Producción

Para validar que la corrección funciona en producción:

1. **Verificar logs**: Revisar que no hay errores de conversión en los logs de `PayloadTransformer`
2. **Comparar timestamps**: Verificar que las fechas enviadas a MININTER están 5 horas atrás respecto a UTC
3. **Validar con MININTER**: Confirmar que las fechas recibidas coinciden con la hora local de Perú

## Fecha de Implementación

- **Fecha de corrección**: [Fecha actual]
- **Archivos modificados**: 
  - `app/Services/PayloadTransformer.php`
  - `tests/Unit/PayloadTransformerTimezoneTest.php` (nuevo)
  - `tests/Feature/Services/PayloadTransformerTest.php` (actualizado)
- **Configuración utilizada**: `config/services.php` (sin cambios, usa configuración existente)

---

**Nota**: Esta corrección es crítica para el funcionamiento correcto del sistema GPS proxy y debe mantenerse en todas las versiones futuras del código. 