# 🧪 Guía de Testing Local - MININTER GPS Proxy

> **Sistema MININTER GPS Proxy** - Guía completa para probar todas las funcionalidades en entorno local antes del deployment

---

## 📋 **Requisitos Previos**

### **Software Requerido:**
- ✅ **PHP 8.3+** con extensiones: pdo, mysql, redis, curl, json, zip
- ✅ **MySQL 8.0+** o **MariaDB 10.3+**
- ✅ **Redis 6.0+** (para colas y cache)
- ✅ **Composer 2.6+**
- ✅ **Node.js 18+** y **npm** (para Filament assets)

### **Servicios Externos (para pruebas reales):**
- 🌐 **GPServer API** - endpoint para consumir datos GPS
- 🏛️ **MININTER Endpoints** - endpoints para enviar datos

---

## 🚀 **1. Setup Inicial del Proyecto**

### **1.1 Configuración del Entorno**

```bash
# 1. Copiar el archivo de configuración
cp .env.example .env

# 2. Generar app key
php artisan key:generate

# 3. CONFIGURAR VARIABLES CRÍTICAS para testing local
# Agregar estas líneas al final de tu .env:
echo "

# =============================================================================
# CONFIGURACIÓN ESPECÍFICA PARA TESTING LOCAL
# =============================================================================

# 🔧 SSL Configuration (CRÍTICO PARA DESARROLLO)
# Deshabilitar verificación SSL para endpoints MININTER con problemas de certificados
MININTER_VERIFY_SSL=false
MININTER_SSL_VERSION=\"TLSv1.2\"

# MININTER Endpoints
MININTER_SERENAZGO_ENDPOINT=\"https://transmision.mininter.gob.pe/retransmisionGPS/ubicacionGPS\"
MININTER_POLICIAL_ENDPOINT=\"https://transmision.mininter.gob.pe/retransmisionpolicial/ubicacion/gps-policial\"
MININTER_TIMEOUT=30
MININTER_CONNECT_TIMEOUT=10

# GPServer (GIPIES)
GPSERVER_BASE_URL=\"https://www.gipies.pe/api/api.php\"
GPSERVER_TIMEOUT=30

# Sistema GPS
GPS_SYNC_INTERVAL=60
GPS_BATCH_SIZE=100
GPS_ENABLE_VALIDATION=true
GPS_ENABLE_PERU_BOUNDS_CHECK=true

# Filament Admin (opcional)
FILAMENT_ADMIN_EMAIL=admin@mininter.gob.pe
FILAMENT_ADMIN_PASSWORD=password123
" >> .env

# 3. Editar variables de entorno críticas
nano .env
```

**Variables obligatorias en `.env`:**

```env
# Base de datos
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mininter_gps
DB_USERNAME=root
DB_PASSWORD=tu_password

# Redis para colas
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Configuración de colas
QUEUE_CONNECTION=redis
CACHE_STORE=redis

# URLs de servicios (PROD o TEST)
GPSERVER_BASE_URL=https://www.gipies.pe/api/api.php
MININTER_SERENAZGO_ENDPOINT=https://transmision.mininter.gob.pe/retransmisionGPS/ubicacionGPS
MININTER_POLICIAL_ENDPOINT=https://transmision.mininter.gob.pe/retransmisionpolicial/ubicacion/gps-policial

# Timeouts y reintentos
GPSERVER_TIMEOUT=30
MININTER_TIMEOUT=30
GPS_SYNC_INTERVAL=60

# Configuración de logging
LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug
```

### **1.2 Instalación de Dependencias**

```bash
# 1. Instalar dependencias PHP
composer install

# 2. Instalar dependencias Node
npm install

# 3. Compilar assets de Filament
npm run build
```

### **1.3 Configuración de Base de Datos**

```bash
# 1. Crear base de datos
mysql -u root -p -e "CREATE DATABASE mininter_gps;"

# 2. Ejecutar migraciones
php artisan migrate

# 3. Ejecutar seeders (datos de prueba)
php artisan db:seed
```

**Resultado esperado:**
```
✅ Usuario admin creado: admin@mininter.gps
🔑 Password: MininterGPS2024!
✅ Municipalidades maestras creadas exitosamente
📊 Total SERENAZGO: 8
📊 Total POLICIAL: 2
```

---

## 🔍 **2. Verificación del Sistema**

### **2.1 Health Check Completo**

```bash
# Verificar estado de todos los servicios
php artisan gps:health-check --detailed
```

**Salida esperada:**
```
🏥 Iniciando verificación de salud del sistema...

📊 Resumen de Health Check:
─────────────────────────────────────
✅ Estado general: HEALTHY

✅ Database: Base de datos funcionando correctamente
   └─ Tiempo respuesta: 25.30ms
   └─ Municipalidades: 10
   └─ Transmisiones: 0

✅ Redis: Redis funcionando correctamente
   └─ Tiempo respuesta: 3.40ms

⚠️  Gpserver: GPServer no responde correctamente
   └─ Tiempo respuesta: 2500.00ms
   └─ Endpoint: https://www.gipies.pe/api/api.php
   └─ Error: Connection timeout

⚠️  Mininter_serenazgo: Endpoint SERENAZGO no responde
   └─ Endpoint: https://transmision.mininter.gob.pe/retransmisionGPS/ubicacionGPS

⚠️  Mininter_policial: Endpoint POLICIAL no responde
   └─ Endpoint: https://transmision.mininter.gob.pe/retransmisionpolicial/ubicacion/gps-policial
```

> 📝 **Nota:** Es normal que GPServer y MININTER fallen si no tienes acceso/tokens reales. Continuaremos con mocks.

### **2.2 Verificar Panel Filament**

```bash
# Levantar servidor local
php artisan serve
```

**Acceder al panel:**
- 🌐 **URL:** http://127.0.0.1:8000/admin
- 👤 **Usuario:** admin@mininter.gps
- 🔑 **Password:** MininterGPS2024!

**Verificaciones en el panel:**
1. ✅ Login exitoso
2. ✅ Dashboard con widgets (transmisiones, stats)
3. ✅ Municipalidades → Ver lista de 10 municipalidades
4. ✅ Transmisiones → Lista vacía (normal al inicio)

---

## 🧪 **3. Testing de Componentes Individuales**

### **3.1 Testing de Servicios (Unit Tests)**

```bash
# Ejecutar todos los tests
php artisan test

# Tests específicos por servicio
php artisan test tests/Feature/Services/ValidationServiceTest.php
php artisan test tests/Feature/Services/PayloadTransformerTest.php
php artisan test tests/Feature/Services/GPServerClientTest.php
```

**Resultado esperado:**
```
PASS  Tests\Feature\Services\ValidationServiceTest
✓ can validate valid GPS coordinates           0.02s
✓ rejects invalid coordinates                  0.01s
✓ validates Peru bounds correctly             0.01s
✓ validates speed limits                      0.01s

PASS  Tests\Feature\Services\PayloadTransformerTest
✓ transforms serenazgo payload correctly      0.02s
✓ transforms policial payload correctly       0.02s
✓ handles missing fields gracefully           0.01s

Tests:  12 passed
Time:   0.89s
```

### **3.2 Testing de Transformaciones**

**Probar transformación SERENAZGO:**

```bash
# Crear un script de prueba rápida
php artisan tinker
```

```php
// En tinker - Test PayloadTransformer
use App\Services\PayloadTransformer;
use App\Models\Municipality;

$municipality = Municipality::serenazgo()->first();
$transformer = app(PayloadTransformer::class);

$testData = [
    'alarma' => '',
    'altitud' => 2793,
    'angulo' => 314,
    'distancia' => 68485,
    'fechaHora' => '12/12/2020 07:42:50',
    'horasMotor' => 14,
    'imei' => '359632109283942',
    'latitud' => -7.1626,
    'longitud' => -78.5189,
    'motion' => true,
    'placa' => 'XXX-123',
    'totalDistancia' => 15,
    'totalHorasMotor' => 0,
    'ubigeo' => '060101',
    'valid' => true,
    'velocidad' => 17
];

$result = $transformer->transformForSerenazgo($testData, $municipality);
dump($result);
// Debe incluir idMunicipalidad

$result = $transformer->transformForPolicial($testData, $municipality);
dump($result);
// Debe incluir idTransmision y codigoComisaria
```

---

## 🔄 **4. Testing del Flujo de Sincronización**

### **4.1 Testing con Datos Mock**

**Crear un test job manual:**

```bash
php artisan tinker
```

```php
// Simular job de sincronización
use App\Jobs\SyncMunicipalityJob;
use App\Models\Municipality;

$municipality = Municipality::serenazgo()->first();
$job = new SyncMunicipalityJob($municipality);

// Ejecutar manualmente (sin queue)
$job->handle();
```

### **4.2 Testing de Queues**

```bash
# En terminal 1: Levantar worker de redis
php artisan queue:work --queue=sync --timeout=60

# En terminal 2: Enviar jobs a la cola
php artisan gps:sync-all --dry-run
```

**Salida esperada:**
```
🚀 Iniciando sincronización de municipalidades...
📊 Municipalidades encontradas: 10

🔍 Modo dry-run activado - No se ejecutarán jobs

📊 Resumen de sincronización:
   • Total municipalidades: 10
   • Jobs enviados: 0 (dry-run)
   • Saltadas: 0
   • Errores: 0
```

**Envío real de jobs:**

```bash
# Enviar jobs reales a la cola
php artisan gps:sync-all --force
```

**En el terminal del worker verás:**
```
[2024-01-12 10:30:15][abc123] Processing: App\Jobs\SyncMunicipalityJob
[2024-01-12 10:30:18][abc123] Processed:  App\Jobs\SyncMunicipalityJob
```

---

## 🎯 **5. Testing con Servicios Externos Mock**

### **5.1 Mock GPServer Response**

**Crear un comando de prueba para simular respuesta GPServer:**

```bash
php artisan tinker
```

```php
// Simular respuesta exitosa de GPServer
use App\Services\GPServerClient;

$client = app(GPServerClient::class);

// Esto fallará en local sin token real, pero puedes mockear:
// En tests/Feature/Services/GPServerClientTest.php
// está el ejemplo de cómo mockear las respuestas HTTP
```

### **5.2 Testing Manual con cURL**

**Probar formato de payload SERENAZGO:**

```bash
# Crear payload de prueba
cat > test-serenazgo.json << 'EOF'
{
  "alarma": "",
  "altitud": 2793,
  "angulo": 314,
  "distancia": 68485,
  "fechaHora": "12/12/2020 07:42:50",
  "horasMotor": 14,
  "idMunicipalidad": "645d2bfa-2e02-4f0c-95a9-2032e9e2a941",
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
EOF

# Test local del formato (no enviará datos reales)
curl -X POST -H "Content-Type: application/json" \
  -d @test-serenazgo.json \
  http://httpbin.org/post
```

---

## 📊 **6. Monitoring y Logging**

### **6.1 Verificar Logs del Sistema**

```bash
# Ver logs en tiempo real
tail -f storage/logs/laravel.log

# Logs específicos GPS (si existen)
tail -f storage/logs/gps/transmissions.log
```

### **6.2 Testing de Log Cleanup**

```bash
# Simular limpieza de logs (dry-run)
php artisan gps:log-cleanup --days=30 --dry-run

# Verificar qué archivos se limpiarían
php artisan gps:log-cleanup --days=7 --dry-run
```

---

## 🔧 **7. Testing de Panel Filament**

### **7.1 Gestión de Municipalidades**

1. **Crear nueva municipalidad:**
   - Ir a Municipalidades → Nueva
   - Llenar todos los campos obligatorios
   - Verificar validaciones

2. **Editar municipalidad existente:**
   - Modificar token_gps
   - Cambiar estado active/inactive
   - Verificar que se guarden cambios

3. **Ver transmisiones:**
   - Acceder a lista de transmisiones
   - Verificar filtros por municipalidad
   - Revisar detalles de payload/response

### **7.2 Dashboard Widgets**

✅ **Verificar que funcionen:**
- Stats Overview (contadores)
- Latest Transmissions (últimas transmisiones)
- Transmission Trend (gráfico de tendencia)

---

## ⚡ **8. Testing de Performance**

### **8.1 Simular Carga de Jobs**

```bash
# Enviar múltiples jobs rápidamente
for i in {1..10}; do
  php artisan gps:sync-all --force
  sleep 5
done

# Monitorear queue
php artisan queue:monitor
```

### **8.2 Verificar Memory Usage**

```bash
# Ejecutar con memory monitoring
php -d memory_limit=128M artisan gps:sync-all
```

---

## 📋 **9. Checklist de Testing Completo**

### **✅ Sistema Base**
- [ ] Health check retorna estado correcto
- [ ] Base de datos conecta y tiene seeders
- [ ] Redis funciona correctamente
- [ ] Panel Filament carga y permite login

### **✅ Servicios Core**
- [ ] ValidationService valida coordenadas
- [ ] PayloadTransformer genera JSON correcto SERENAZGO/POLICIAL
- [ ] GPServerClient maneja timeouts y errores
- [ ] MininterClient envía formato correcto

### **✅ Jobs y Queues**
- [ ] SyncMunicipalityJob se ejecuta sin errores
- [ ] Queue worker procesa jobs correctamente
- [ ] Failed jobs se manejan apropiadamente

### **✅ Panel Administración**
- [ ] CRUD de municipalidades funciona
- [ ] Lista de transmisiones muestra datos
- [ ] Widgets del dashboard cargan

### **✅ Comandos Artisan**
- [ ] `gps:health-check` funciona
- [ ] `gps:sync-all` envía jobs
- [ ] `gps:log-cleanup` simula limpieza

### **✅ Testing Automatizado**
- [ ] Unit tests pasan (php artisan test)
- [ ] Feature tests cubren casos principales

---

## 🚨 **10. Troubleshooting Común**

### **Error: Redis Connection Failed**
```bash
# Verificar Redis
redis-cli ping
# Debe retornar PONG

# Reiniciar Redis si es necesario
sudo systemctl restart redis
```

### **Error: Queue Worker Not Processing**
```bash
# Verificar jobs en cola
php artisan queue:monitor

# Limpiar jobs fallidos
php artisan queue:flush

# Reiniciar workers
php artisan queue:restart
```

### **Error: GPServer/MININTER Timeout**
- ✅ Normal en testing local sin tokens reales
- ✅ Los mocks en tests cubren estos casos
- ✅ En producción necesitarás tokens/IPs autorizadas

### **Error: Filament Login Failed**
```bash
# Recrear usuario admin
php artisan db:seed --class=AdminUserSeeder
```

---

## 📚 **11. Próximos Pasos**

Una vez que tengas todo funcionando en local:

1. **🧪 Completar Fase 6 Testing**
   - Tests unitarios faltantes
   - Tests de integración end-to-end
   - Tests de carga

2. **📦 Preparar Deployment**
   - Environment de staging
   - Configuración de producción
   - CI/CD pipeline

3. **📊 Monitoring de Producción**
   - Logs centralizados
   - Alertas automáticas
   - Métricas de performance

---

## 🎯 **¿Todo funciona? ¡Excelente!**

Si has completado esta guía exitosamente, tu sistema está listo para:
- ✅ Desarrollo local completo
- ✅ Testing de todas las funcionalidades
- ✅ Preparación para deployment

**Siguiente paso:** Implementar los tests faltantes de la Fase 6 usando TaskMaster.

---

> 📝 **Documentación creada para MININTER GPS Proxy System**  
> **Versión:** 1.0 | **Fecha:** 2024-01-12  
> **Para actualizaciones:** Revisar documentacion/ 