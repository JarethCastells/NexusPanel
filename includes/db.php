<?php
require_once __DIR__ . '/config.php';

// Fallbacks para hostings sin extension mbstring habilitada.
if (!function_exists('mb_strtolower')) {
    function mb_strtolower($string, $encoding = null) {
        return strtolower((string)$string);
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr($string, $start, $length = null, $encoding = null) {
        $string = (string)$string;
        $start = (int)$start;
        if ($length === null) {
            return substr($string, $start);
        }
        return substr($string, $start, (int)$length);
    }
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Migraciones idempotentes para nuevas funciones del demo.
    ensureNexusSchemaV2($pdo);
    ensureLogisticaMasivaSchema($pdo);
    ensureLogisticaInteligenteSchema($pdo);
    ensureReporteGerencialLogisticaSchema($pdo);
    ensureEmailInboxSchema($pdo);
    ensureCatalogoClienteSeed($pdo);
} catch (PDOException $e) {
    // Mensaje amigable si no puede conectar
    die('
    <div style="font-family:sans-serif;background:#0a0f1c;color:#f0f6ff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:40px;">
    <div style="background:#0d1422;border:1px solid rgba(239,68,68,0.3);border-radius:16px;padding:32px;max-width:500px;width:100%;">
        <h2 style="color:#fca5a5;margin-bottom:12px;">Error de conexion a MySQL</h2>
        <p style="color:#aaa;font-size:14px;margin-bottom:20px;">' . htmlspecialchars($e->getMessage()) . '</p>
        <div style="background:#111827;border-radius:10px;padding:16px;font-size:13px;color:#94a3b8;">
            <strong style="color:#e2e8f0;">Pasos para solucionar:</strong><br><br>
            1. Asegurate de que MySQL este iniciado en XAMPP<br>
            2. Abre phpMyAdmin: <a href="http://localhost/phpmyadmin" style="color:#00d4ff;">localhost/phpmyadmin</a><br>
            3. Crea una base de datos llamada <code style="color:#10b981;">nexuspanel</code><br>
            4. Edita <code style="color:#10b981;">includes/config.php</code> con tus credenciales<br>
            5. Ve a <a href="setup_db.php" style="color:#00d4ff;">setup_db.php</a> para crear las tablas
        </div>
    </div>
    </div>');
}

function ensureCatalogoClienteSeed(PDO $pdo): void {
    try {
        if (!tableExists($pdo, 'productos') || !tableExists($pdo, 'subcategorias_globales')) {
            return;
        }
        if (!columnExists($pdo, 'productos', 'categoria_slug') || !columnExists($pdo, 'productos', 'subcategoria_id')) {
            return;
        }

        $insSub = $pdo->prepare("
            INSERT IGNORE INTO subcategorias_globales (nombre, slug, descripcion, activa)
            VALUES (?, ?, 'Subcategoria base', 1)
        ");
        $insSub->execute(['Alimento', 'alimento']);
        $insSub->execute(['Comida', 'comida']);
        $insSub->execute(['Proteina', 'proteina']);
        $insSub->execute(['Engorda', 'engorda']);
        $insSub->execute(['Lacteo', 'lacteo']);
        $insSub->execute(['Suplemento', 'suplemento']);

        // Asegurar mezcla de categorias en BD.
        $dist = $pdo->query("
            SELECT
                COUNT(*) AS total,
                COUNT(DISTINCT categoria_slug) AS cats,
                SUM(CASE WHEN categoria_slug='pollo' THEN 1 ELSE 0 END) AS pollo,
                SUM(CASE WHEN categoria_slug='vaca' THEN 1 ELSE 0 END) AS vaca,
                SUM(CASE WHEN categoria_slug='cerdo' THEN 1 ELSE 0 END) AS cerdo
            FROM productos
        ")->fetch() ?: [];
        $total = (int)($dist['total'] ?? 0);
        $cats = (int)($dist['cats'] ?? 0);
        $soloUna = $total > 0 && (
            (int)($dist['pollo'] ?? 0) === $total ||
            (int)($dist['vaca'] ?? 0) === $total ||
            (int)($dist['cerdo'] ?? 0) === $total
        );
        if ($total > 0 && ($cats < 3 || $soloUna)) {
            $pdo->exec("
                UPDATE productos
                SET categoria_slug = CASE
                    WHEN MOD(id,3) = 1 THEN 'pollo'
                    WHEN MOD(id,3) = 2 THEN 'vaca'
                    ELSE 'cerdo'
                END
            ");
        }

        // Repartir subcategorias variadas y persistentes.
        $subs = $pdo->query("
            SELECT id, nombre
            FROM subcategorias_globales
            WHERE activa = 1
            ORDER BY id ASC
        ")->fetchAll();
        if (empty($subs)) {
            return;
        }
        $prodIds = $pdo->query("SELECT id FROM productos ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
        $up = $pdo->prepare("UPDATE productos SET subcategoria_id = ?, subcategoria = ? WHERE id = ?");
        $subCount = count($subs);
        foreach ($prodIds as $i => $pidTmp) {
            $pid = (int)$pidTmp;
            if ($pid <= 0) continue;
            $sub = $subs[$i % $subCount];
            $sid = (int)($sub['id'] ?? 0);
            $sn = trim((string)($sub['nombre'] ?? 'Alimento'));
            if ($sid > 0) {
                $up->execute([$sid, $sn !== '' ? $sn : 'Alimento', $pid]);
            }
        }
    } catch (Throwable $e) {
        // No detener la app por un seed opcional.
    }
}

function tableExists(PDO $pdo, string $table): bool {
    $st = $pdo->prepare("
        SELECT COUNT(*) AS c
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = ?
    ");
    $st->execute([$table]);
    return (int)$st->fetchColumn() > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    $st = $pdo->prepare("
        SELECT COUNT(*) AS c
        FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
    ");
    $st->execute([$table, $column]);
    return (int)$st->fetchColumn() > 0;
}

function indexExists(PDO $pdo, string $table, string $index): bool {
    $st = $pdo->prepare("
        SELECT COUNT(*) AS c
        FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
    ");
    $st->execute([$table, $index]);
    return (int)$st->fetchColumn() > 0;
}

function constraintExists(PDO $pdo, string $table, string $constraint): bool {
    $st = $pdo->prepare("
        SELECT COUNT(*) AS c
        FROM information_schema.table_constraints
        WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ?
    ");
    $st->execute([$table, $constraint]);
    return (int)$st->fetchColumn() > 0;
}

function ensureEmailInboxSchema(PDO $pdo): void {
    static $ran = false;
    if ($ran) return;
    $ran = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_inbox_messages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            message_id VARCHAR(255) NOT NULL,
            imap_uid BIGINT UNSIGNED NULL,
            from_email VARCHAR(255) NULL,
            from_name VARCHAR(255) NULL,
            subject VARCHAR(500) NULL,
            body_text LONGTEXT NULL,
            body_html LONGTEXT NULL,
            received_at DATETIME NULL,
            fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            is_unseen TINYINT(1) NOT NULL DEFAULT 1,
            source_mailbox VARCHAR(120) NOT NULL DEFAULT 'INBOX',
            raw_headers LONGTEXT NULL,
            UNIQUE KEY uq_email_inbox_message_id (message_id),
            KEY idx_email_inbox_received_at (received_at),
            KEY idx_email_inbox_unseen (is_unseen)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function normalizarFechaMysql(?string $valor, bool $permitirSoloFecha = false): ?string {
    $valor = trim((string)$valor);
    if ($valor === '') {
        return null;
    }

    $valor = str_replace('T', ' ', $valor);
    $formatos = ['Y-m-d H:i:s', 'Y-m-d H:i'];
    if ($permitirSoloFecha) {
        $formatos[] = 'Y-m-d';
    }

    foreach ($formatos as $formato) {
        $dt = DateTime::createFromFormat($formato, $valor);
        if ($dt instanceof DateTime) {
            if ($formato === 'Y-m-d') {
                $dt->setTime(0, 0, 0);
            }
            return $dt->format('Y-m-d H:i:s');
        }
    }

    $timestamp = strtotime($valor);
    if ($timestamp !== false) {
        return date('Y-m-d H:i:s', $timestamp);
    }

    return null;
}

function fechaMysqlAhora(): string {
    return date('Y-m-d H:i:s');
}

function ensureNexusSchemaV2(PDO $pdo): void {
    static $ran = false;
    if ($ran) return;
    $ran = true;

    try {
        // Permitir rol inventario/distribucion sin depender del dump original.
        $pdo->exec("
            ALTER TABLE usuarios
            MODIFY COLUMN rol ENUM('administrador','inventario','distribucion','operador','cliente') NOT NULL
        ");

        // 1) Catalogo de especies.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS especies (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(80) NOT NULL UNIQUE,
                slug VARCHAR(80) NOT NULL UNIQUE,
                descripcion VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        if (!columnExists($pdo, 'especies', 'descripcion')) {
            $pdo->exec("ALTER TABLE especies ADD COLUMN descripcion VARCHAR(255) NULL AFTER slug");
        }

        $especies = [
            ['Alimento', 'alimento', 'Categoria base de insumos y nutricion'],
            ['Comida', 'comida', 'Categoria base de alimento terminado'],
        ];
        $insEsp = $pdo->prepare("INSERT IGNORE INTO especies (nombre, slug, descripcion) VALUES (?, ?, ?)");
        foreach ($especies as [$nombre, $slug, $descripcion]) {
            $insEsp->execute([$nombre, $slug, $descripcion]);
        }

        $inventarioEmail = 'inventario@demo.com';
        $inventarioExiste = $pdo->prepare("SELECT id FROM usuarios WHERE LOWER(email) = ? LIMIT 1");
        $inventarioExiste->execute([$inventarioEmail]);
        if (!$inventarioExiste->fetchColumn()) {
            $pdo->prepare("
                INSERT INTO usuarios (nombre, email, password, rol, domicilio, edad, telefono, lat, lng, zona_radio, activo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ")->execute([
                'Coordinacion Inventario',
                $inventarioEmail,
                password_hash('inventario123', PASSWORD_DEFAULT),
                'inventario',
                'Centro logistico NexusPanel',
                30,
                '5500000000',
                19.4326,
                -99.1332,
                9999,
            ]);
        }

        // 2) Productos: especie y ajustes por cliente.
        if (!columnExists($pdo, 'productos', 'especie_id')) {
            $pdo->exec("ALTER TABLE productos ADD COLUMN especie_id INT NULL AFTER imagen");
        }
        if (!columnExists($pdo, 'productos', 'cliente_ajustable')) {
            $pdo->exec("ALTER TABLE productos ADD COLUMN cliente_ajustable TINYINT(1) NOT NULL DEFAULT 1 AFTER especie_id");
        }
        if (!columnExists($pdo, 'productos', 'subcategoria')) {
            $pdo->exec("ALTER TABLE productos ADD COLUMN subcategoria VARCHAR(80) NULL AFTER especie_id");
        }
        if (!columnExists($pdo, 'productos', 'unidad_medida')) {
            $pdo->exec("ALTER TABLE productos ADD COLUMN unidad_medida VARCHAR(20) NOT NULL DEFAULT 'piezas' AFTER stock");
        }
        if (!columnExists($pdo, 'productos', 'fecha_caducidad')) {
            $pdo->exec("ALTER TABLE productos ADD COLUMN fecha_caducidad DATE NULL AFTER unidad_medida");
        }
        if (!columnExists($pdo, 'productos', 'fecha_ingreso')) {
            $pdo->exec("ALTER TABLE productos ADD COLUMN fecha_ingreso DATETIME NULL AFTER fecha_caducidad");
        }
        if (!columnExists($pdo, 'productos', 'lote_activo_id')) {
            $pdo->exec("ALTER TABLE productos ADD COLUMN lote_activo_id INT NULL AFTER fecha_ingreso");
        }
        if (!indexExists($pdo, 'productos', 'idx_productos_especie')) {
            $pdo->exec("CREATE INDEX idx_productos_especie ON productos(especie_id)");
        }
        if (!indexExists($pdo, 'productos', 'idx_productos_subcategoria')) {
            $pdo->exec("CREATE INDEX idx_productos_subcategoria ON productos(subcategoria)");
        }
        if (!indexExists($pdo, 'productos', 'idx_productos_lote_activo')) {
            $pdo->exec("CREATE INDEX idx_productos_lote_activo ON productos(lote_activo_id)");
        }
        $pdo->exec("UPDATE productos SET fecha_ingreso = created_at WHERE fecha_ingreso IS NULL");
        $pdo->exec("UPDATE productos SET subcategoria = 'General' WHERE subcategoria IS NULL OR TRIM(subcategoria) = ''");

        // Clasificacion inicial automatica (categorias genericas).
        $alimentoId = (int)$pdo->query("SELECT id FROM especies WHERE slug='alimento' LIMIT 1")->fetchColumn();
        $comidaId = (int)$pdo->query("SELECT id FROM especies WHERE slug='comida' LIMIT 1")->fetchColumn();
        if ($alimentoId > 0 && $comidaId > 0) {
            $pdo->exec("UPDATE productos SET especie_id = {$alimentoId} WHERE especie_id IS NULL");
            $pdo->exec("UPDATE productos SET especie_id = {$comidaId} WHERE UPPER(nombre) LIKE '%FOOD%' OR UPPER(nombre) LIKE '%COMIDA%'");
            $pdo->exec("UPDATE productos SET especie_id = {$alimentoId} WHERE especie_id IS NULL");
        }

        $pdo->exec("
            UPDATE productos
            SET subcategoria = CASE
                WHEN UPPER(nombre) LIKE '%VACUN%' THEN 'Vacuna'
                WHEN UPPER(nombre) LIKE '%ANTIBI%' OR UPPER(nombre) LIKE '%MED%' OR UPPER(nombre) LIKE '%IONO%' THEN 'Medicamento'
                WHEN UPPER(nombre) LIKE '%PREMIX%' OR UPPER(nombre) LIKE '%MIX%' OR UPPER(nombre) LIKE '%ALIM%' OR UPPER(nombre) LIKE '%NUTR%' THEN 'Alimento'
                WHEN UPPER(nombre) LIKE '%KIT%' OR UPPER(nombre) LIKE '%ACCESOR%' OR UPPER(nombre) LIKE '%EQUIP%' THEN 'Accesorio'
                ELSE COALESCE(subcategoria, 'General')
            END
            WHERE subcategoria IS NULL OR subcategoria = '' OR subcategoria = 'General'
        ");

        // 2b) Catalogo de subcategorias por categoria.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS subcategorias_catalogo (
                id INT AUTO_INCREMENT PRIMARY KEY,
                especie_id INT NOT NULL,
                nombre VARCHAR(80) NOT NULL,
                slug VARCHAR(80) NOT NULL,
                descripcion VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY ux_subcat_especie_slug (especie_id, slug),
                KEY idx_subcat_especie (especie_id),
                CONSTRAINT fk_subcat_especie FOREIGN KEY (especie_id) REFERENCES especies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $subcatsBase = ['Alimento balanceado', 'Comida humeda', 'Medicamento', 'Vacuna', 'Accesorio', 'General'];
        $insSubcat = $pdo->prepare("
            INSERT IGNORE INTO subcategorias_catalogo (especie_id, nombre, slug, descripcion)
            VALUES (?, ?, ?, ?)
        ");
        $especiesRows = $pdo->query("SELECT id, nombre FROM especies ORDER BY id ASC")->fetchAll();
        foreach ($especiesRows as $espRow) {
            $espId = (int)$espRow['id'];
            foreach ($subcatsBase as $sc) {
                $slug = slugCatalogo($sc);
                $insSubcat->execute([$espId, $sc, $slug, 'Subcategoria base para ' . ($espRow['nombre'] ?? 'categoria')]);
            }
        }

        $productosConSub = $pdo->query("
            SELECT DISTINCT especie_id, subcategoria
            FROM productos
            WHERE especie_id IS NOT NULL AND subcategoria IS NOT NULL AND TRIM(subcategoria) <> ''
        ")->fetchAll();
        foreach ($productosConSub as $ps) {
            $espId = (int)$ps['especie_id'];
            $nombreSub = trim((string)$ps['subcategoria']);
            if ($espId <= 0 || $nombreSub === '') {
                continue;
            }
            $insSubcat->execute([$espId, $nombreSub, slugCatalogo($nombreSub), null]);
        }

        // 2d) Modelo fijo categoria (pollo/vaca/cerdo) + subcategoria global.
        if (!columnExists($pdo, 'productos', 'categoria_slug')) {
            $pdo->exec("ALTER TABLE productos ADD COLUMN categoria_slug VARCHAR(20) NULL AFTER especie_id");
        }
        if (!columnExists($pdo, 'productos', 'subcategoria_id')) {
            $pdo->exec("ALTER TABLE productos ADD COLUMN subcategoria_id INT NULL AFTER subcategoria");
        }
        if (!indexExists($pdo, 'productos', 'idx_productos_categoria_slug')) {
            $pdo->exec("CREATE INDEX idx_productos_categoria_slug ON productos(categoria_slug)");
        }
        if (!indexExists($pdo, 'productos', 'idx_productos_subcategoria_id')) {
            $pdo->exec("CREATE INDEX idx_productos_subcategoria_id ON productos(subcategoria_id)");
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS subcategorias_globales (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(80) NOT NULL,
                slug VARCHAR(80) NOT NULL UNIQUE,
                descripcion VARCHAR(255) NULL,
                activa TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $insSubGlobalBase = $pdo->prepare("
            INSERT IGNORE INTO subcategorias_globales (nombre, slug, descripcion, activa)
            VALUES (?, ?, ?, 1)
        ");
        $insSubGlobalBase->execute(['Alimento', 'alimento', 'Subcategoria base']);
        $insSubGlobalBase->execute(['Comida', 'comida', 'Subcategoria base']);
        $insSubGlobalBase->execute(['Proteina', 'proteina', 'Subcategoria base']);
        $insSubGlobalBase->execute(['Engorda', 'engorda', 'Subcategoria base']);
        $insSubGlobalBase->execute(['Lacteo', 'lacteo', 'Subcategoria base']);
        $insSubGlobalBase->execute(['Suplemento', 'suplemento', 'Subcategoria base']);

        // Backfill categoria_slug a solo pollo/vaca/cerdo.
        $pdo->exec("
            UPDATE productos p
            LEFT JOIN especies e ON e.id = p.especie_id
            SET p.categoria_slug = CASE
                WHEN LOWER(COALESCE(e.slug, '')) IN ('pollo', 'pollos') OR LOWER(COALESCE(e.nombre, '')) LIKE '%pollo%' THEN 'pollo'
                WHEN LOWER(COALESCE(e.slug, '')) IN ('vaca', 'vacas') OR LOWER(COALESCE(e.nombre, '')) LIKE '%vaca%' THEN 'vaca'
                WHEN LOWER(COALESCE(e.slug, '')) IN ('cerdo', 'cerdos') OR LOWER(COALESCE(e.nombre, '')) LIKE '%cerdo%' THEN 'cerdo'
                ELSE p.categoria_slug
            END
        ");
        $pdo->exec("
            UPDATE productos
            SET categoria_slug = CASE
                WHEN UPPER(nombre) LIKE '%CERD%' OR UPPER(nombre) LIKE '%PORC%' OR UPPER(nombre) LIKE '%PIG%' THEN 'cerdo'
                WHEN UPPER(nombre) LIKE '%VACA%' OR UPPER(nombre) LIKE '%BOV%' OR UPPER(nombre) LIKE '%BEEF%' OR UPPER(nombre) LIKE '%LECHE%' THEN 'vaca'
                WHEN UPPER(nombre) LIKE '%POLLO%' OR UPPER(nombre) LIKE '%AVI%' OR UPPER(nombre) LIKE '%BROIL%' THEN 'pollo'
                ELSE categoria_slug
            END
            WHERE categoria_slug IS NULL OR categoria_slug = '' OR categoria_slug NOT IN ('pollo','vaca','cerdo')
        ");
        $pdo->exec("
            UPDATE productos
            SET categoria_slug = CASE
                WHEN MOD(id,3) = 0 THEN 'cerdo'
                WHEN MOD(id,3) = 1 THEN 'pollo'
                ELSE 'vaca'
            END
            WHERE categoria_slug IS NULL OR categoria_slug = '' OR categoria_slug NOT IN ('pollo','vaca','cerdo')
        ");

        // Backfill subcategorias globales y referencia por id.
        $subsExistentes = $pdo->query("
            SELECT DISTINCT TRIM(COALESCE(subcategoria, '')) AS nombre
            FROM productos
            WHERE subcategoria IS NOT NULL AND TRIM(subcategoria) <> ''
        ")->fetchAll(PDO::FETCH_COLUMN);
        $insSubGlobal = $pdo->prepare("
            INSERT IGNORE INTO subcategorias_globales (nombre, slug, activa)
            VALUES (?, ?, 1)
        ");
        foreach ($subsExistentes as $nombreSub) {
            $nombreSub = trim((string)$nombreSub);
            if ($nombreSub === '') {
                continue;
            }
            $slugSub = slugCatalogo($nombreSub);
            if (strpos($slugSub, 'aliment') !== false) $slugSub = 'alimento';
            if (strpos($slugSub, 'comida') !== false) $slugSub = 'comida';
            if (strpos($slugSub, 'prote') !== false) $slugSub = 'proteina';
            $nombreCanon = $slugSub === 'proteina' ? 'Proteina' : ($slugSub === 'alimento' ? 'Alimento' : ($slugSub === 'comida' ? 'Comida' : $nombreSub));
            $insSubGlobal->execute([$nombreCanon, $slugSub]);
        }

        $stSubBySlug = $pdo->prepare("SELECT id FROM subcategorias_globales WHERE slug = ? LIMIT 1");
        $stProdSub = $pdo->query("SELECT id, TRIM(COALESCE(subcategoria,'')) AS subcategoria FROM productos")->fetchAll();
        $upProdSub = $pdo->prepare("UPDATE productos SET subcategoria_id = ? WHERE id = ?");
        foreach ($stProdSub as $psub) {
            $prodId = (int)$psub['id'];
            if ($prodId <= 0) continue;
            $nom = trim((string)$psub['subcategoria']);
            $slugSub = $nom !== '' ? slugCatalogo($nom) : 'alimento';
            if (strpos($slugSub, 'aliment') !== false) $slugSub = 'alimento';
            if (strpos($slugSub, 'comida') !== false) $slugSub = 'comida';
            if (strpos($slugSub, 'prote') !== false) $slugSub = 'proteina';
            $stSubBySlug->execute([$slugSub]);
            $subId = (int)$stSubBySlug->fetchColumn();
            if ($subId <= 0) {
                $insSubGlobal->execute([ucfirst($slugSub), $slugSub]);
                $stSubBySlug->execute([$slugSub]);
                $subId = (int)$stSubBySlug->fetchColumn();
            }
            if ($subId > 0) {
                $upProdSub->execute([$subId, $prodId]);
            }
        }
        $subAlimentoId = (int)$pdo->query("SELECT id FROM subcategorias_globales WHERE slug = 'alimento' LIMIT 1")->fetchColumn();
        if ($subAlimentoId > 0) {
            $pdo->prepare("UPDATE productos SET subcategoria_id = ? WHERE subcategoria_id IS NULL OR subcategoria_id = 0")
                ->execute([$subAlimentoId]);
        }

        // Forzar mezcla real en BD para evitar que todo quede en "pollo".
        $dist = $pdo->query("
            SELECT
                COUNT(*) AS total,
                COUNT(DISTINCT categoria_slug) AS categorias_distintas,
                SUM(CASE WHEN categoria_slug='pollo' THEN 1 ELSE 0 END) AS c_pollo,
                SUM(CASE WHEN categoria_slug='vaca' THEN 1 ELSE 0 END) AS c_vaca,
                SUM(CASE WHEN categoria_slug='cerdo' THEN 1 ELSE 0 END) AS c_cerdo
            FROM productos
        ")->fetch() ?: [];
        $totalProd = (int)($dist['total'] ?? 0);
        $catsDist = (int)($dist['categorias_distintas'] ?? 0);
        $soloUnaCategoria = $totalProd > 0 && (
            (int)($dist['c_pollo'] ?? 0) === $totalProd ||
            (int)($dist['c_vaca'] ?? 0) === $totalProd ||
            (int)($dist['c_cerdo'] ?? 0) === $totalProd
        );
        if ($totalProd > 0 && ($catsDist < 3 || $soloUnaCategoria)) {
            $pdo->exec("
                UPDATE productos
                SET categoria_slug = CASE
                    WHEN MOD(id,3) = 1 THEN 'pollo'
                    WHEN MOD(id,3) = 2 THEN 'vaca'
                    ELSE 'cerdo'
                END
            ");
        }

        // Repartir subcategorias de forma variada y persistente en BD.
        $subRowsReparto = $pdo->query("
            SELECT id, slug, nombre
            FROM subcategorias_globales
            WHERE activa = 1
            ORDER BY FIELD(slug, 'alimento','comida','proteina','engorda','lacteo','suplemento') DESC, id ASC
        ")->fetchAll();
        if (!empty($subRowsReparto)) {
            $productosIds = $pdo->query("SELECT id FROM productos ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
            $upSubProd = $pdo->prepare("UPDATE productos SET subcategoria_id = ?, subcategoria = ? WHERE id = ?");
            $totalSubs = count($subRowsReparto);
            foreach ($productosIds as $idxProd => $prodIdTmp) {
                $prodId = (int)$prodIdTmp;
                if ($prodId <= 0) continue;
                $slot = $idxProd % $totalSubs;
                $subRow = $subRowsReparto[$slot];
                $subIdAssign = (int)($subRow['id'] ?? 0);
                $subNombreAssign = trim((string)($subRow['nombre'] ?? 'Alimento'));
                if ($subIdAssign > 0) {
                    $upSubProd->execute([$subIdAssign, $subNombreAssign !== '' ? $subNombreAssign : 'Alimento', $prodId]);
                }
            }
        }

        // Enforce categoria fija y FK global.
        $pdo->exec("ALTER TABLE productos MODIFY COLUMN categoria_slug ENUM('pollo','vaca','cerdo') NOT NULL DEFAULT 'pollo'");
        if ($subAlimentoId > 0) {
            $pdo->exec("ALTER TABLE productos MODIFY COLUMN subcategoria_id INT NOT NULL");
        }
        if (!constraintExists($pdo, 'productos', 'fk_productos_subcategoria_global')) {
            $pdo->exec("
                ALTER TABLE productos
                ADD CONSTRAINT fk_productos_subcategoria_global
                FOREIGN KEY (subcategoria_id) REFERENCES subcategorias_globales(id)
                ON UPDATE CASCADE
            ");
        }

        // 2c) Lotes por producto.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS producto_lotes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                producto_id INT NOT NULL,
                lote_numero INT NOT NULL,
                fecha_ingreso DATETIME NOT NULL,
                fecha_caducidad DATE NOT NULL,
                unidades INT NOT NULL DEFAULT 0,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                created_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY ux_lote_producto_numero (producto_id, lote_numero),
                KEY idx_lote_producto (producto_id),
                KEY idx_lote_caducidad (fecha_caducidad),
                CONSTRAINT fk_lote_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
                CONSTRAINT fk_lote_usuario FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Crear lote base para productos existentes que aun no tienen lotes.
        $productosSinLote = $pdo->query("
            SELECT p.id, p.stock, p.fecha_ingreso, p.fecha_caducidad
            FROM productos p
            LEFT JOIN producto_lotes l ON l.producto_id = p.id
            WHERE l.id IS NULL
        ")->fetchAll();
        $insLoteLegacy = $pdo->prepare("
            INSERT INTO producto_lotes (producto_id, lote_numero, fecha_ingreso, fecha_caducidad, unidades, created_by)
            VALUES (?, 1, ?, ?, ?, NULL)
        ");
        foreach ($productosSinLote as $pl) {
            $pid = (int)$pl['id'];
            if ($pid <= 0) {
                continue;
            }
            $fechaIng = normalizarFechaMysql((string)($pl['fecha_ingreso'] ?? ''), true) ?? fechaMysqlAhora();
            $fechaCad = trim((string)($pl['fecha_caducidad'] ?? ''));
            if ($fechaCad === '' || $fechaCad === '0000-00-00') {
                $fechaCad = date('Y-m-d', strtotime('+180 days'));
            }
            $stock = max(0, (int)($pl['stock'] ?? 0));
            $insLoteLegacy->execute([$pid, $fechaIng, $fechaCad, $stock]);
        }

        // Sincronizar stock con suma de lotes y lote activo por defecto.
        $productosSync = $pdo->query("SELECT id FROM productos")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($productosSync as $productoIdSync) {
            $productoIdSync = (int)$productoIdSync;
            if ($productoIdSync <= 0) {
                continue;
            }
            recalcularStockProductoDesdeLotes($pdo, $productoIdSync);
            $stLoteActivo = $pdo->prepare("
                SELECT id FROM producto_lotes
                WHERE producto_id = ?
                ORDER BY fecha_ingreso DESC, lote_numero DESC
                LIMIT 1
            ");
            $stLoteActivo->execute([$productoIdSync]);
            $loteId = (int)$stLoteActivo->fetchColumn();
            if ($loteId > 0) {
                $pdo->prepare("UPDATE productos SET lote_activo_id = COALESCE(lote_activo_id, ?) WHERE id = ?")
                    ->execute([$loteId, $productoIdSync]);
            }
        }

        // 3) Pedidos: tipo, fecha requerida y notas.
        if (!columnExists($pdo, 'pedidos', 'tipo_pedido')) {
            $pdo->exec("ALTER TABLE pedidos ADD COLUMN tipo_pedido ENUM('formal','informal') NOT NULL DEFAULT 'formal' AFTER estado");
        }
        if (!columnExists($pdo, 'pedidos', 'folio_hex')) {
            $pdo->exec("ALTER TABLE pedidos ADD COLUMN folio_hex VARCHAR(12) NULL AFTER id");
        }
        if (!columnExists($pdo, 'pedidos', 'fecha_requerida')) {
            $pdo->exec("ALTER TABLE pedidos ADD COLUMN fecha_requerida DATETIME NULL AFTER domicilio_entrega");
        }
        if (!columnExists($pdo, 'pedidos', 'notas')) {
            $pdo->exec("ALTER TABLE pedidos ADD COLUMN notas TEXT NULL AFTER fecha_requerida");
        }
        if (!indexExists($pdo, 'pedidos', 'idx_pedidos_tipo')) {
            $pdo->exec("CREATE INDEX idx_pedidos_tipo ON pedidos(tipo_pedido)");
        }
        if (!columnExists($pdo, 'pedidos', 'prioridad')) {
            $pdo->exec("ALTER TABLE pedidos ADD COLUMN prioridad ENUM('baja','media','alta','urgente') NOT NULL DEFAULT 'media' AFTER tipo_pedido");
        }
        if (!columnExists($pdo, 'pedidos', 'fecha_programada')) {
            $pdo->exec("ALTER TABLE pedidos ADD COLUMN fecha_programada DATETIME NULL AFTER fecha_requerida");
        }
        if (!columnExists($pdo, 'pedidos', 'transporte_linea')) {
            $pdo->exec("ALTER TABLE pedidos ADD COLUMN transporte_linea VARCHAR(120) NULL AFTER fecha_programada");
        }
        if (!columnExists($pdo, 'pedidos', 'pl_documento')) {
            $pdo->exec("ALTER TABLE pedidos ADD COLUMN pl_documento VARCHAR(140) NULL AFTER transporte_linea");
        }
        if (!columnExists($pdo, 'pedidos', 'area_flujo')) {
            $pdo->exec("ALTER TABLE pedidos ADD COLUMN area_flujo ENUM('ventas','embarque','logistica','entrega') NOT NULL DEFAULT 'ventas' AFTER pl_documento");
        }
        if (!columnExists($pdo, 'pedidos', 'cfdi_status')) {
            $pdo->exec("ALTER TABLE pedidos ADD COLUMN cfdi_status ENUM('pendiente','timbrado','error') NOT NULL DEFAULT 'pendiente' AFTER area_flujo");
        }
        if (!columnExists($pdo, 'pedidos', 'cfdi_uuid')) {
            $pdo->exec("ALTER TABLE pedidos ADD COLUMN cfdi_uuid VARCHAR(64) NULL AFTER cfdi_status");
        }
        if (!columnExists($pdo, 'pedidos', 'cfdi_pdf_url')) {
            $pdo->exec("ALTER TABLE pedidos ADD COLUMN cfdi_pdf_url VARCHAR(255) NULL AFTER cfdi_uuid");
        }
        if (!columnExists($pdo, 'pedidos', 'cfdi_xml_url')) {
            $pdo->exec("ALTER TABLE pedidos ADD COLUMN cfdi_xml_url VARCHAR(255) NULL AFTER cfdi_pdf_url");
        }
        if (!indexExists($pdo, 'pedidos', 'idx_pedidos_prioridad')) {
            $pdo->exec("CREATE INDEX idx_pedidos_prioridad ON pedidos(prioridad)");
        }
        if (!indexExists($pdo, 'pedidos', 'ux_pedidos_folio_hex')) {
            $pdo->exec("CREATE UNIQUE INDEX ux_pedidos_folio_hex ON pedidos(folio_hex)");
        }
        if (!indexExists($pdo, 'pedidos', 'idx_pedidos_fecha_programada')) {
            $pdo->exec("CREATE INDEX idx_pedidos_fecha_programada ON pedidos(fecha_programada)");
        }
        $pdo->exec("UPDATE pedidos SET folio_hex = LPAD(UPPER(HEX(id)), 8, '0') WHERE folio_hex IS NULL OR folio_hex = ''");
        $pdo->exec("UPDATE pedidos SET created_at = COALESCE(updated_at, NOW()) WHERE created_at IS NULL OR created_at = '0000-00-00 00:00:00'");
        $pdo->exec("UPDATE pedidos SET updated_at = COALESCE(created_at, NOW()) WHERE updated_at IS NULL OR updated_at = '0000-00-00 00:00:00'");
        $pdo->exec("UPDATE pedidos SET fecha_requerida = NULL WHERE fecha_requerida = '0000-00-00 00:00:00'");
        $pdo->exec("UPDATE pedidos SET fecha_programada = NULL WHERE fecha_programada = '0000-00-00 00:00:00'");

        // 4) Ajustes por item.
        if (!columnExists($pdo, 'pedido_items', 'ajuste_cliente')) {
            $pdo->exec("ALTER TABLE pedido_items ADD COLUMN ajuste_cliente VARCHAR(255) NULL AFTER precio_unit");
        }

        // 5) Historial agregado por cliente/producto.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cliente_producto_historial (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cliente_id INT NOT NULL,
                producto_id INT NOT NULL,
                cantidad_total INT NOT NULL DEFAULT 0,
                veces_pedido INT NOT NULL DEFAULT 0,
                ultima_fecha DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY ux_cliente_producto (cliente_id, producto_id),
                KEY idx_historial_cliente (cliente_id),
                KEY idx_historial_producto (producto_id),
                CONSTRAINT fk_historial_cliente FOREIGN KEY (cliente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
                CONSTRAINT fk_historial_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // 6) Historial de estados por pedido.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pedido_historial_estados (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pedido_id INT NOT NULL,
                estado VARCHAR(40) NOT NULL,
                nota VARCHAR(255) NULL,
                usuario_id INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_phe_pedido (pedido_id),
                KEY idx_phe_estado (estado),
                CONSTRAINT fk_phe_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
                CONSTRAINT fk_phe_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // 7) Movimientos de inventario para auditoria y reporte.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS inventario_movimientos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                producto_id INT NOT NULL,
                tipo ENUM('entrada','salida','ajuste','reserva','liberacion') NOT NULL,
                cantidad INT NOT NULL,
                stock_anterior INT NOT NULL,
                stock_nuevo INT NOT NULL,
                referencia_tipo VARCHAR(40) NULL,
                referencia_id INT NULL,
                nota VARCHAR(255) NULL,
                created_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_im_producto (producto_id),
                KEY idx_im_fecha (created_at),
                KEY idx_im_ref (referencia_tipo, referencia_id),
                CONSTRAINT fk_im_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
                CONSTRAINT fk_im_user FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // 8) Notificaciones de flujo (interno/canales externos).
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS notificaciones_eventos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pedido_id INT NOT NULL,
                cliente_id INT NOT NULL,
                canal ENUM('interno','email','whatsapp','sms','llamada') NOT NULL,
                evento VARCHAR(60) NOT NULL,
                mensaje TEXT NOT NULL,
                estado ENUM('pendiente','enviado','error') NOT NULL DEFAULT 'pendiente',
                metadata_json TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                sent_at DATETIME NULL,
                KEY idx_ne_pedido (pedido_id),
                KEY idx_ne_cliente (cliente_id),
                KEY idx_ne_evento (evento),
                CONSTRAINT fk_ne_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
                CONSTRAINT fk_ne_cliente FOREIGN KEY (cliente_id) REFERENCES usuarios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // 9) Catalogo de lineas de transporte.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS transporte_lineas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(120) NOT NULL UNIQUE,
                capacidad_kg INT NULL,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $insTransporte = $pdo->prepare("INSERT IGNORE INTO transporte_lineas (nombre, capacidad_kg, activo) VALUES (?, ?, 1)");
        $insTransporte->execute(['Linea Norte', 12000]);
        $insTransporte->execute(['Linea Centro', 10000]);
        $insTransporte->execute(['Linea Golfo', 8000]);

        // 10) Registro de timbrado CFDI (stub para integrar PAC real).
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cfdi_eventos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pedido_id INT NOT NULL,
                estado ENUM('pendiente','timbrado','error') NOT NULL DEFAULT 'pendiente',
                mensaje VARCHAR(255) NULL,
                payload_json TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_cfdi_pedido (pedido_id),
                KEY idx_cfdi_estado (estado),
                CONSTRAINT fk_cfdi_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // 11) Bitacora de auditoria por usuario/rol.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS auditoria_eventos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NULL,
                rol VARCHAR(40) NULL,
                modulo VARCHAR(60) NOT NULL,
                accion VARCHAR(80) NOT NULL,
                referencia_tipo VARCHAR(40) NULL,
                referencia_id INT NULL,
                detalles TEXT NULL,
                ip_origen VARCHAR(64) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_ae_usuario (usuario_id),
                KEY idx_ae_modulo (modulo),
                KEY idx_ae_fecha (created_at),
                CONSTRAINT fk_ae_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Throwable $e) {
        // No bloqueamos la app por una migracion parcial en demo.
    }
}

function ensureLogisticaMasivaSchema(PDO $pdo): void {
    static $ran = false;
    if ($ran) return;
    $ran = true;

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS logistica_lotes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                folio VARCHAR(40) NOT NULL UNIQUE,
                manager_id INT NOT NULL,
                modo_envio ENUM('flotilla','paqueteria') NOT NULL DEFAULT 'flotilla',
                paqueteria VARCHAR(80) NULL,
                costo_pct DECIMAL(8,2) NOT NULL DEFAULT 0,
                costo_por_caja DECIMAL(12,2) NOT NULL DEFAULT 0,
                costo_estimado_total DECIMAL(14,2) NOT NULL DEFAULT 0,
                estado ENUM('borrador','pendiente_aceptacion','aceptado','en_embarque','cerrado','cancelado') NOT NULL DEFAULT 'borrador',
                fecha_salida_programada DATE NULL,
                fecha_entrega_estimada DATE NULL,
                hora_salida DATETIME NULL,
                hora_cierre DATETIME NULL,
                observaciones VARCHAR(500) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_ll_estado (estado),
                KEY idx_ll_manager (manager_id),
                CONSTRAINT fk_ll_manager FOREIGN KEY (manager_id) REFERENCES usuarios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS logistica_lote_pedidos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                lote_id INT NOT NULL,
                pedido_id INT NOT NULL,
                ruta_grupo VARCHAR(80) NULL,
                cajas_sugeridas INT NOT NULL DEFAULT 1,
                cajas_asignadas INT NOT NULL DEFAULT 1,
                operador_id INT NULL,
                estado ENUM('sugerido','asignado','en_ruta','entregado_parcial','entregado','cancelado') NOT NULL DEFAULT 'sugerido',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY ux_llp_lote_pedido (lote_id, pedido_id),
                KEY idx_llp_operador (operador_id),
                KEY idx_llp_estado (estado),
                CONSTRAINT fk_llp_lote FOREIGN KEY (lote_id) REFERENCES logistica_lotes(id) ON DELETE CASCADE,
                CONSTRAINT fk_llp_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
                CONSTRAINT fk_llp_operador FOREIGN KEY (operador_id) REFERENCES usuarios(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS logistica_lote_operadores (
                id INT AUTO_INCREMENT PRIMARY KEY,
                lote_id INT NOT NULL,
                operador_id INT NOT NULL,
                camion_alias VARCHAR(80) NULL,
                capacidad_cajas INT NOT NULL DEFAULT 25,
                cajas_asignadas INT NOT NULL DEFAULT 0,
                hora_salida DATETIME NULL,
                hora_entrega DATETIME NULL,
                estado ENUM('pendiente','cargando','en_ruta','cerrado') NOT NULL DEFAULT 'pendiente',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY ux_llo_lote_operador (lote_id, operador_id),
                KEY idx_llo_estado (estado),
                CONSTRAINT fk_llo_lote FOREIGN KEY (lote_id) REFERENCES logistica_lotes(id) ON DELETE CASCADE,
                CONSTRAINT fk_llo_operador FOREIGN KEY (operador_id) REFERENCES usuarios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS logistica_eventos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                lote_id INT NOT NULL,
                usuario_id INT NULL,
                tipo_evento VARCHAR(60) NOT NULL,
                detalle VARCHAR(500) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_le_lote (lote_id),
                CONSTRAINT fk_le_lote FOREIGN KEY (lote_id) REFERENCES logistica_lotes(id) ON DELETE CASCADE,
                CONSTRAINT fk_le_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        if (tableExists($pdo, 'logistica_lote_operadores')) {
            if (!columnExists($pdo, 'logistica_lote_operadores', 'checklist_salida_json')) {
                $pdo->exec("ALTER TABLE logistica_lote_operadores ADD COLUMN checklist_salida_json TEXT NULL AFTER estado");
            }
            if (!columnExists($pdo, 'logistica_lote_operadores', 'checklist_cierre_json')) {
                $pdo->exec("ALTER TABLE logistica_lote_operadores ADD COLUMN checklist_cierre_json TEXT NULL AFTER checklist_salida_json");
            }
            if (!columnExists($pdo, 'logistica_lote_operadores', 'fecha_operacion')) {
                $pdo->exec("ALTER TABLE logistica_lote_operadores ADD COLUMN fecha_operacion DATE NULL AFTER checklist_cierre_json");
            }
        }

        if (tableExists($pdo, 'logistica_lote_pedidos') && !columnExists($pdo, 'logistica_lote_pedidos', 'prioridad_ruta')) {
            $pdo->exec("ALTER TABLE logistica_lote_pedidos ADD COLUMN prioridad_ruta INT NOT NULL DEFAULT 100 AFTER ruta_grupo");
        }
    } catch (Throwable $e) {
        // Evitar bloquear app por migracion parcial.
    }
}

function slugCatalogo(string $valor): string {
    $valor = trim(mb_strtolower($valor, 'UTF-8'));
    if ($valor === '') {
        return 'general';
    }
    $map = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
        'ñ' => 'n'
    ];
    $valor = strtr($valor, $map);
    $valor = preg_replace('/[^a-z0-9]+/u', '-', $valor);
    $valor = trim((string)$valor, '-');
    return $valor !== '' ? $valor : 'general';
}

function textoNormalizadoCatalogo(string $valor): string {
    $valor = preg_replace('/\s+/u', ' ', trim(mb_strtolower($valor, 'UTF-8')));
    return (string)$valor;
}

function subcategoriaExisteEnCategoria(PDO $pdo, int $categoriaId, string $subcategoria): bool {
    if ($categoriaId <= 0 || trim($subcategoria) === '') {
        return false;
    }
    $normalizada = textoNormalizadoCatalogo($subcategoria);
    $st = $pdo->prepare("
        SELECT nombre
        FROM subcategorias_catalogo
        WHERE especie_id = ?
    ");
    $st->execute([$categoriaId]);
    $rows = $st->fetchAll(PDO::FETCH_COLUMN);
    foreach ($rows as $rowName) {
        if (textoNormalizadoCatalogo((string)$rowName) === $normalizada) {
            return true;
        }
    }
    return false;
}

function upsertCategoriaCatalogo(PDO $pdo, string $nombre, ?string $descripcion = null): int {
    $nombre = trim($nombre);
    if ($nombre === '') {
        throw new RuntimeException('La categoria no puede estar vacia.');
    }
    $slug = slugCatalogo($nombre);

    $st = $pdo->prepare("SELECT id, nombre FROM especies WHERE slug = ? OR LOWER(nombre) = LOWER(?) LIMIT 1");
    $st->execute([$slug, $nombre]);
    $row = $st->fetch();
    if ($row) {
        $id = (int)$row['id'];
        if ($descripcion !== null && trim($descripcion) !== '') {
            $pdo->prepare("UPDATE especies SET descripcion = ? WHERE id = ?")
                ->execute([mb_substr(trim($descripcion), 0, 255), $id]);
        }
        return $id;
    }

    $pdo->prepare("INSERT INTO especies (nombre, slug, descripcion) VALUES (?, ?, ?)")
        ->execute([$nombre, $slug, $descripcion !== null ? mb_substr(trim($descripcion), 0, 255) : null]);
    return (int)$pdo->lastInsertId();
}

function upsertSubcategoriaCatalogo(PDO $pdo, int $categoriaId, string $nombre, ?string $descripcion = null): int {
    $nombre = trim($nombre);
    if ($categoriaId <= 0 || $nombre === '') {
        throw new RuntimeException('Subcategoria invalida.');
    }

    $st = $pdo->prepare("SELECT id, nombre FROM subcategorias_catalogo WHERE especie_id = ?");
    $st->execute([$categoriaId]);
    $normalizadaNueva = textoNormalizadoCatalogo($nombre);
    foreach ($st->fetchAll() as $row) {
        if (textoNormalizadoCatalogo((string)$row['nombre']) === $normalizadaNueva) {
            $idExistente = (int)$row['id'];
            if ($descripcion !== null && trim($descripcion) !== '') {
                $pdo->prepare("UPDATE subcategorias_catalogo SET descripcion = ? WHERE id = ?")
                    ->execute([mb_substr(trim($descripcion), 0, 255), $idExistente]);
            }
            return $idExistente;
        }
    }

    $slug = slugCatalogo($nombre);
    $pdo->prepare("
        INSERT INTO subcategorias_catalogo (especie_id, nombre, slug, descripcion)
        VALUES (?, ?, ?, ?)
    ")->execute([
        $categoriaId,
        $nombre,
        $slug,
        $descripcion !== null ? mb_substr(trim($descripcion), 0, 255) : null
    ]);

    return (int)$pdo->lastInsertId();
}

function obtenerSiguienteNumeroLote(PDO $pdo, int $productoId): int {
    $st = $pdo->prepare("SELECT COALESCE(MAX(lote_numero), 0) FROM producto_lotes WHERE producto_id = ?");
    $st->execute([$productoId]);
    return (int)$st->fetchColumn() + 1;
}

function crearLoteProducto(
    PDO $pdo,
    int $productoId,
    int $unidades,
    string $fechaCaducidad,
    ?string $fechaIngreso = null,
    ?int $createdBy = null
): int {
    if ($productoId <= 0) {
        throw new RuntimeException('Producto invalido para lote.');
    }
    if ($unidades < 0) {
        throw new RuntimeException('Unidades del lote no validas.');
    }
    $fechaCad = trim($fechaCaducidad);
    if ($fechaCad === '') {
        throw new RuntimeException('La fecha de caducidad es obligatoria para el lote.');
    }

    $fechaIng = normalizarFechaMysql($fechaIngreso, true) ?? fechaMysqlAhora();
    $loteNumero = obtenerSiguienteNumeroLote($pdo, $productoId);

    $st = $pdo->prepare("
        INSERT INTO producto_lotes (producto_id, lote_numero, fecha_ingreso, fecha_caducidad, unidades, created_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $st->execute([
        $productoId,
        $loteNumero,
        $fechaIng,
        $fechaCad,
        $unidades,
        $createdBy && $createdBy > 0 ? $createdBy : null
    ]);
    $loteId = (int)$pdo->lastInsertId();

    $pdo->prepare("UPDATE productos SET lote_activo_id = ? WHERE id = ?")
        ->execute([$loteId, $productoId]);
    recalcularStockProductoDesdeLotes($pdo, $productoId);

    return $loteId;
}

function recalcularStockProductoDesdeLotes(PDO $pdo, int $productoId): int {
    if ($productoId <= 0) {
        return 0;
    }
    $st = $pdo->prepare("SELECT COALESCE(SUM(unidades), 0) FROM producto_lotes WHERE producto_id = ?");
    $st->execute([$productoId]);
    $stock = (int)$st->fetchColumn();
    $pdo->prepare("UPDATE productos SET stock = ? WHERE id = ?")->execute([$stock, $productoId]);
    return $stock;
}

function obtenerLotesProducto(PDO $pdo, int $productoId): array {
    $st = $pdo->prepare("
        SELECT id, lote_numero, fecha_ingreso, fecha_caducidad, unidades
        FROM producto_lotes
        WHERE producto_id = ?
        ORDER BY fecha_ingreso DESC, lote_numero DESC
    ");
    $st->execute([$productoId]);
    return $st->fetchAll();
}

function recalcularHistorialCliente(PDO $pdo, int $clienteId): void {
    if ($clienteId <= 0) return;

    $del = $pdo->prepare("DELETE FROM cliente_producto_historial WHERE cliente_id = ?");
    $del->execute([$clienteId]);

    $ins = $pdo->prepare("
        INSERT INTO cliente_producto_historial (cliente_id, producto_id, cantidad_total, veces_pedido, ultima_fecha)
        SELECT
            p.cliente_id,
            pi.producto_id,
            SUM(pi.cantidad) AS cantidad_total,
            COUNT(DISTINCT p.id) AS veces_pedido,
            MAX(p.created_at) AS ultima_fecha
        FROM pedidos p
        JOIN pedido_items pi ON pi.pedido_id = p.id
        WHERE p.cliente_id = ?
          AND p.estado <> 'cancelado'
        GROUP BY p.cliente_id, pi.producto_id
    ");
    $ins->execute([$clienteId]);
}

function registrarHistorialPedido(PDO $pdo, int $pedidoId, string $estado, ?int $usuarioId = null, ?string $nota = null): void {
    if ($pedidoId <= 0 || $estado === '') return;
    $st = $pdo->prepare("
        INSERT INTO pedido_historial_estados (pedido_id, estado, nota, usuario_id)
        VALUES (?, ?, ?, ?)
    ");
    $st->execute([
        $pedidoId,
        mb_substr($estado, 0, 40),
        $nota !== null && $nota !== '' ? mb_substr($nota, 0, 255) : null,
        $usuarioId && $usuarioId > 0 ? $usuarioId : null
    ]);
}

function registrarMovimientoInventario(
    PDO $pdo,
    int $productoId,
    string $tipo,
    int $cantidad,
    int $stockAnterior,
    int $stockNuevo,
    ?string $referenciaTipo = null,
    ?int $referenciaId = null,
    ?string $nota = null,
    ?int $usuarioId = null
): void {
    if ($productoId <= 0 || $cantidad <= 0) return;
    $tiposValidos = ['entrada', 'salida', 'ajuste', 'reserva', 'liberacion'];
    if (!in_array($tipo, $tiposValidos, true)) $tipo = 'ajuste';

    $st = $pdo->prepare("
        INSERT INTO inventario_movimientos
            (producto_id, tipo, cantidad, stock_anterior, stock_nuevo, referencia_tipo, referencia_id, nota, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $st->execute([
        $productoId,
        $tipo,
        $cantidad,
        $stockAnterior,
        $stockNuevo,
        $referenciaTipo !== null ? mb_substr($referenciaTipo, 0, 40) : null,
        $referenciaId && $referenciaId > 0 ? $referenciaId : null,
        $nota !== null && $nota !== '' ? mb_substr($nota, 0, 255) : null,
        $usuarioId && $usuarioId > 0 ? $usuarioId : null
    ]);
}

function registrarNotificacionEvento(
    PDO $pdo,
    int $pedidoId,
    int $clienteId,
    string $canal,
    string $evento,
    string $mensaje,
    string $estado = 'pendiente',
    ?array $meta = null
): void {
    if ($pedidoId <= 0 || $clienteId <= 0 || trim($mensaje) === '') return;
    $canalesValidos = ['interno', 'email', 'whatsapp', 'sms', 'llamada'];
    if (!in_array($canal, $canalesValidos, true)) $canal = 'interno';
    $estadosValidos = ['pendiente', 'enviado', 'error'];
    if (!in_array($estado, $estadosValidos, true)) $estado = 'pendiente';

    $st = $pdo->prepare("
        INSERT INTO notificaciones_eventos
            (pedido_id, cliente_id, canal, evento, mensaje, estado, metadata_json)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $st->execute([
        $pedidoId,
        $clienteId,
        $canal,
        mb_substr($evento !== '' ? $evento : 'evento', 0, 60),
        $mensaje,
        $estado,
        $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null
    ]);
}

function registrarNotificacionesEstandarPedido(
    PDO $pdo,
    int $pedidoId,
    int $clienteId,
    string $evento,
    string $mensaje,
    ?array $meta = null
): void {
    $canales = ['interno', 'whatsapp', 'sms', 'email'];
    foreach ($canales as $canal) {
        registrarNotificacionEvento($pdo, $pedidoId, $clienteId, $canal, $evento, $mensaje, 'pendiente', $meta);
    }
}

function registrarEventoCfdi(PDO $pdo, int $pedidoId, string $estado, ?string $mensaje = null, ?array $payload = null): void {
    if ($pedidoId <= 0) return;
    if (!in_array($estado, ['pendiente', 'timbrado', 'error'], true)) $estado = 'pendiente';
    $st = $pdo->prepare("
        INSERT INTO cfdi_eventos (pedido_id, estado, mensaje, payload_json)
        VALUES (?, ?, ?, ?)
    ");
    $st->execute([
        $pedidoId,
        $estado,
        $mensaje !== null && $mensaje !== '' ? mb_substr($mensaje, 0, 255) : null,
        $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null
    ]);
}

function registrarAuditoria(
    PDO $pdo,
    ?int $usuarioId,
    ?string $rol,
    string $modulo,
    string $accion,
    ?string $referenciaTipo = null,
    ?int $referenciaId = null,
    ?string $detalles = null
): void {
    if ($modulo === '' || $accion === '') return;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $st = $pdo->prepare("
        INSERT INTO auditoria_eventos
            (usuario_id, rol, modulo, accion, referencia_tipo, referencia_id, detalles, ip_origen)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $st->execute([
        $usuarioId && $usuarioId > 0 ? $usuarioId : null,
        $rol !== null && $rol !== '' ? mb_substr($rol, 0, 40) : null,
        mb_substr($modulo, 0, 60),
        mb_substr($accion, 0, 80),
        $referenciaTipo !== null && $referenciaTipo !== '' ? mb_substr($referenciaTipo, 0, 40) : null,
        $referenciaId && $referenciaId > 0 ? $referenciaId : null,
        $detalles !== null && $detalles !== '' ? mb_substr($detalles, 0, 1000) : null,
        $ip !== null ? mb_substr((string)$ip, 0, 64) : null
    ]);
}

function generarFolioHex(PDO $pdo): string {
    if (!columnExists($pdo, 'pedidos', 'folio_hex')) {
        return strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
    }
    for ($i = 0; $i < 8; $i++) {
        $folio = strtoupper(bin2hex(random_bytes(4)));
        $st = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE folio_hex = ?");
        $st->execute([$folio]);
        if ((int)$st->fetchColumn() === 0) {
            return $folio;
        }
    }
    return strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
}

function distanciaKm(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $r = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $r * 2 * asin(sqrt($a));
}

function buscarOperadorCercano(PDO $pdo, ?float $lat, ?float $lng): ?array {
    if ($lat === null || $lng === null) return null;
    $st = $pdo->query("
        SELECT id, nombre, lat, lng, zona_radio, activo
        FROM usuarios
        WHERE rol = 'operador' AND activo = 1
    ");
    $ops = $st->fetchAll();
    if (!$ops) return null;

    $best = null;
    $bestDist = PHP_FLOAT_MAX;
    foreach ($ops as $op) {
        if ($op['lat'] === null || $op['lng'] === null) continue;
        $dist = distanciaKm((float)$lat, (float)$lng, (float)$op['lat'], (float)$op['lng']);
        $radio = (float)($op['zona_radio'] ?? 50);
        if ($dist <= $radio && $dist < $bestDist) {
            $bestDist = $dist;
            $best = $op;
            $best['distancia_km'] = round($dist, 2);
        }
    }
    return $best;
}

function ensureLogisticaInteligenteSchema(PDO $pdo): void {
    static $ran = false;
    if ($ran) return;
    $ran = true;

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `logistica_lineas` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `nombre` VARCHAR(100) NOT NULL UNIQUE,
                `prioridad` INT DEFAULT 1,
                `rastreo_tiempo_real` TINYINT(1) DEFAULT 0,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `logistica_unidades` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `linea_id` INT NOT NULL,
                `tipo` VARCHAR(50) NOT NULL,
                `tipo_caja` VARCHAR(50) NOT NULL,
                `capacidad_kg` INT NOT NULL,
                `peso_minimo` INT DEFAULT 0,
                `mercancia_permitida` VARCHAR(255) DEFAULT 'general',
                CONSTRAINT fk_unidad_linea FOREIGN KEY (linea_id) REFERENCES logistica_lineas(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `logistica_rutas` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `linea_id` INT NOT NULL,
                `origen` VARCHAR(100) NOT NULL,
                `destino` VARCHAR(100) NOT NULL,
                `frecuencia` VARCHAR(50) DEFAULT 'diaria',
                `tarifa_fija` DECIMAL(10,2) DEFAULT 0.00,
                `tiempo_estimado_hrs` INT DEFAULT 24,
                `cumplimiento_pct` INT DEFAULT 100,
                CONSTRAINT fk_ruta_linea FOREIGN KEY (linea_id) REFERENCES logistica_lineas(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `logistica_citas` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `pedido_id` INT NOT NULL,
                `linea_id` INT NOT NULL,
                `fecha_cita` DATETIME NOT NULL,
                `estado` ENUM('programada','completada','cancelada') DEFAULT 'programada',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_cita_linea FOREIGN KEY (linea_id) REFERENCES logistica_lineas(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Seed if empty
        $count = $pdo->query("SELECT COUNT(*) FROM logistica_lineas")->fetchColumn();
        if ($count == 0) {
            $pdo->exec("
                INSERT INTO `logistica_lineas` (`nombre`, `prioridad`, `rastreo_tiempo_real`) VALUES 
                ('Loxagon', 1, 1),
                ('Bisonte', 2, 0),
                ('Tres Guerras', 3, 1),
                ('Austral', 4, 0);

                INSERT INTO `logistica_unidades` (`linea_id`, `tipo`, `tipo_caja`, `capacidad_kg`, `peso_minimo`, `mercancia_permitida`) VALUES 
                (1, 'TORTON', 'Seca', 15000, 5000, 'General, Alimentos'),
                (1, '3.5 TON', 'Seca', 3500, 500, 'General'),
                (1, '1 TON', 'Seca', 1000, 0, 'General'),
                (2, 'TORTON', 'Seca', 15000, 2000, 'General'),
                (2, '1 TON', 'Refrigerada', 1000, 0, 'Perecederos'),
                (3, 'TORTON', 'Seca', 15000, 2000, 'General'),
                (4, '3.5 TON', 'Plataforma', 3500, 500, 'Construcción');

                INSERT INTO `logistica_rutas` (`linea_id`, `origen`, `destino`, `frecuencia`, `tarifa_fija`, `tiempo_estimado_hrs`, `cumplimiento_pct`) VALUES 
                (1, 'CDMX', 'Guadalajara', 'diaria', 15000.00, 12, 98),
                (1, 'Monterrey', 'CDMX', 'diaria', 18000.00, 14, 95),
                (2, 'Querétaro', 'Puebla', 'semanal', 8000.00, 6, 92),
                (3, 'Veracruz', 'CDMX', 'bajo demanda', 12000.00, 8, 90),
                (4, 'Mérida', 'Cancún', 'diaria', 5000.00, 4, 99);
            ");
        }
    } catch (Throwable $e) {}
}

function ensureReporteGerencialLogisticaSchema(PDO $pdo): void {
    static $ran = false;
    if ($ran) return;
    $ran = true;

    try {
        // Campos de pedido necesarios para reportes gerenciales.
        if (tableExists($pdo, 'pedidos')) {
            if (!columnExists($pdo, 'pedidos', 'folio_hex')) {
                $pdo->exec("ALTER TABLE pedidos ADD COLUMN folio_hex VARCHAR(12) NULL AFTER id");
            }
            if (!columnExists($pdo, 'pedidos', 'fecha_programada')) {
                $pdo->exec("ALTER TABLE pedidos ADD COLUMN fecha_programada DATETIME NULL AFTER updated_at");
            }
            if (!columnExists($pdo, 'pedidos', 'transporte_linea')) {
                $pdo->exec("ALTER TABLE pedidos ADD COLUMN transporte_linea VARCHAR(120) NULL AFTER fecha_programada");
            }
            if (!columnExists($pdo, 'pedidos', 'logistica_ruta_id')) {
                $pdo->exec("ALTER TABLE pedidos ADD COLUMN logistica_ruta_id INT NULL AFTER transporte_linea");
            }
            if (!columnExists($pdo, 'pedidos', 'ruta_origen')) {
                $pdo->exec("ALTER TABLE pedidos ADD COLUMN ruta_origen VARCHAR(120) NULL AFTER logistica_ruta_id");
            }
            if (!columnExists($pdo, 'pedidos', 'ruta_destino')) {
                $pdo->exec("ALTER TABLE pedidos ADD COLUMN ruta_destino VARCHAR(120) NULL AFTER ruta_origen");
            }
            if (!columnExists($pdo, 'pedidos', 'ruta_zona')) {
                $pdo->exec("ALTER TABLE pedidos ADD COLUMN ruta_zona VARCHAR(80) NULL AFTER ruta_destino");
            }
            if (!indexExists($pdo, 'pedidos', 'idx_pedidos_transporte_linea')) {
                $pdo->exec("CREATE INDEX idx_pedidos_transporte_linea ON pedidos(transporte_linea)");
            }
            if (!indexExists($pdo, 'pedidos', 'idx_pedidos_logistica_ruta')) {
                $pdo->exec("CREATE INDEX idx_pedidos_logistica_ruta ON pedidos(logistica_ruta_id)");
            }
            $pdo->exec("UPDATE pedidos SET folio_hex = LPAD(UPPER(HEX(id)), 8, '0') WHERE folio_hex IS NULL OR folio_hex = ''");
        }

        // Ampliar catalogos logisticos existentes en vez de crear otro modelo paralelo.
        if (tableExists($pdo, 'logistica_lineas')) {
            if (!columnExists($pdo, 'logistica_lineas', 'dias_operacion')) {
                $pdo->exec("ALTER TABLE logistica_lineas ADD COLUMN dias_operacion VARCHAR(80) NOT NULL DEFAULT 'Lunes a sabado' AFTER rastreo_tiempo_real");
            }
            if (!columnExists($pdo, 'logistica_lineas', 'horario_carga')) {
                $pdo->exec("ALTER TABLE logistica_lineas ADD COLUMN horario_carga VARCHAR(40) NOT NULL DEFAULT '08:00-18:00' AFTER dias_operacion");
            }
            if (!columnExists($pdo, 'logistica_lineas', 'horario_entrega')) {
                $pdo->exec("ALTER TABLE logistica_lineas ADD COLUMN horario_entrega VARCHAR(40) NOT NULL DEFAULT '09:00-19:00' AFTER horario_carga");
            }
            if (!columnExists($pdo, 'logistica_lineas', 'tiempo_carga_descarga_min')) {
                $pdo->exec("ALTER TABLE logistica_lineas ADD COLUMN tiempo_carga_descarga_min INT NOT NULL DEFAULT 60 AFTER horario_entrega");
            }
            if (!columnExists($pdo, 'logistica_lineas', 'requiere_cita')) {
                $pdo->exec("ALTER TABLE logistica_lineas ADD COLUMN requiere_cita TINYINT(1) NOT NULL DEFAULT 1 AFTER tiempo_carga_descarga_min");
            }
            if (!columnExists($pdo, 'logistica_lineas', 'anticipacion_cita_hrs')) {
                $pdo->exec("ALTER TABLE logistica_lineas ADD COLUMN anticipacion_cita_hrs INT NOT NULL DEFAULT 24 AFTER requiere_cita");
            }
            if (!columnExists($pdo, 'logistica_lineas', 'costo_relativo')) {
                $pdo->exec("ALTER TABLE logistica_lineas ADD COLUMN costo_relativo ENUM('bajo','medio','alto') NOT NULL DEFAULT 'medio' AFTER anticipacion_cita_hrs");
            }
            if (!columnExists($pdo, 'logistica_lineas', 'activo')) {
                $pdo->exec("ALTER TABLE logistica_lineas ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER costo_relativo");
            }
        }

        if (tableExists($pdo, 'logistica_rutas')) {
            if (!columnExists($pdo, 'logistica_rutas', 'zona')) {
                $pdo->exec("ALTER TABLE logistica_rutas ADD COLUMN zona VARCHAR(80) NULL AFTER destino");
            }
            if (!columnExists($pdo, 'logistica_rutas', 'tipo_cobro')) {
                $pdo->exec("ALTER TABLE logistica_rutas ADD COLUMN tipo_cobro VARCHAR(80) NOT NULL DEFAULT 'Ruta fija + peso' AFTER frecuencia");
            }
            if (!columnExists($pdo, 'logistica_rutas', 'costo_km_adicional')) {
                $pdo->exec("ALTER TABLE logistica_rutas ADD COLUMN costo_km_adicional DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER tarifa_fija");
            }
            if (!columnExists($pdo, 'logistica_rutas', 'recargo_combustible')) {
                $pdo->exec("ALTER TABLE logistica_rutas ADD COLUMN recargo_combustible DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER costo_km_adicional");
            }
            if (!columnExists($pdo, 'logistica_rutas', 'recargo_maniobras')) {
                $pdo->exec("ALTER TABLE logistica_rutas ADD COLUMN recargo_maniobras DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER recargo_combustible");
            }
            if (!columnExists($pdo, 'logistica_rutas', 'recargo_zona_extendida')) {
                $pdo->exec("ALTER TABLE logistica_rutas ADD COLUMN recargo_zona_extendida DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER recargo_maniobras");
            }
            if (!columnExists($pdo, 'logistica_rutas', 'tiempo_min_hrs')) {
                $pdo->exec("ALTER TABLE logistica_rutas ADD COLUMN tiempo_min_hrs INT NOT NULL DEFAULT 12 AFTER tiempo_estimado_hrs");
            }
            if (!columnExists($pdo, 'logistica_rutas', 'tiempo_max_hrs')) {
                $pdo->exec("ALTER TABLE logistica_rutas ADD COLUMN tiempo_max_hrs INT NOT NULL DEFAULT 48 AFTER tiempo_min_hrs");
            }
            if (!columnExists($pdo, 'logistica_rutas', 'sla_hrs')) {
                $pdo->exec("ALTER TABLE logistica_rutas ADD COLUMN sla_hrs INT NOT NULL DEFAULT 24 AFTER tiempo_max_hrs");
            }
            if (!columnExists($pdo, 'logistica_rutas', 'activo')) {
                $pdo->exec("ALTER TABLE logistica_rutas ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER cumplimiento_pct");
            }
            if (!indexExists($pdo, 'logistica_rutas', 'idx_logistica_rutas_destino')) {
                $pdo->exec("CREATE INDEX idx_logistica_rutas_destino ON logistica_rutas(destino)");
            }
        }

        normalizarCatalogoLineasLogistica($pdo);

        $lineasDemo = [
            ['Loxagon', 1, 1, 'bajo', 24],
            ['Bisonte', 2, 1, 'medio', 48],
            ['Tres Guerras', 3, 0, 'bajo', 24],
            ['Austral', 4, 1, 'alto', 72],
        ];
        $insLinea = $pdo->prepare("
            INSERT INTO logistica_lineas
                (nombre, prioridad, rastreo_tiempo_real, dias_operacion, horario_carga, horario_entrega, tiempo_carga_descarga_min, requiere_cita, anticipacion_cita_hrs, costo_relativo, activo)
            VALUES (?, ?, ?, 'Lunes a sabado', '08:00-18:00', '09:00-19:00', 60, 1, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
                prioridad = VALUES(prioridad),
                rastreo_tiempo_real = VALUES(rastreo_tiempo_real),
                anticipacion_cita_hrs = VALUES(anticipacion_cita_hrs),
                costo_relativo = VALUES(costo_relativo),
                activo = 1
        ");
        foreach ($lineasDemo as $lineaDemo) {
            $insLinea->execute($lineaDemo);
        }

        $ids = [];
        $lineasRows = $pdo->query("SELECT id, nombre FROM logistica_lineas")->fetchAll();
        foreach ($lineasRows as $lr) {
            $ids[(string)$lr['nombre']] = (int)$lr['id'];
        }

        $unidadesDemo = [
            ['Loxagon', 'Torton', 'Seca', 14000, 500, 'General'],
            ['Loxagon', 'Camioneta', 'Seca', 1500, 100, 'Fragil'],
            ['Bisonte', 'Trailer', 'Refrigerada', 28000, 1000, 'Alimentos'],
            ['Bisonte', 'Full', 'Seca', 45000, 2000, 'General'],
            ['Tres Guerras', 'Rabon', 'Seca', 8000, 300, 'Fragil'],
            ['Tres Guerras', 'Trailer', 'Plataforma', 30000, 1200, 'Peligrosa'],
            ['Austral', 'Full', 'Plataforma', 45000, 2000, 'General'],
            ['Austral', 'Torton', 'Refrigerada', 13000, 500, 'Alimentos'],
        ];
        $insUnidad = $pdo->prepare("
            INSERT INTO logistica_unidades (linea_id, tipo, tipo_caja, capacidad_kg, peso_minimo, mercancia_permitida)
            SELECT ?, ?, ?, ?, ?, ?
            WHERE NOT EXISTS (
                SELECT 1 FROM logistica_unidades
                WHERE linea_id = ? AND tipo = ? AND tipo_caja = ?
            )
        ");
        foreach ($unidadesDemo as [$nombreLinea, $tipo, $caja, $capacidad, $pesoMin, $mercancia]) {
            if (!isset($ids[$nombreLinea])) continue;
            $lineaId = $ids[$nombreLinea];
            $insUnidad->execute([$lineaId, $tipo, $caja, $capacidad, $pesoMin, $mercancia, $lineaId, $tipo, $caja]);
        }

        $rutasDemo = [
            ['Loxagon', 'CDMX', 'Puebla', 'Centro', 'diaria', 'Ruta fija + peso', 5200, 18, 450, 300, 0, 24, 20, 28, 24, 96],
            ['Loxagon', 'Queretaro', 'Leon', 'Bajio', 'diaria', 'Ruta fija', 6900, 18, 460, 280, 0, 24, 16, 26, 24, 96],
            ['Bisonte', 'CDMX', 'Monterrey', 'Norte', 'semanal', 'Ruta fija + peso', 23800, 28, 1800, 750, 900, 72, 60, 84, 72, 89],
            ['Bisonte', 'Toluca', 'Guadalajara', 'Occidente', 'semanal', 'Ruta fija + dimension', 16200, 25, 1150, 650, 500, 48, 40, 58, 48, 91],
            ['Tres Guerras', 'CDMX', 'Puebla', 'Centro', 'diaria', 'Ruta fija', 5050, 18, 430, 280, 0, 24, 20, 30, 24, 92],
            ['Tres Guerras', 'CDMX', 'Merida', 'Sureste', 'bajo demanda', 'Ruta fija + peso', 31800, 32, 2400, 900, 1200, 96, 84, 110, 96, 86],
            ['Austral', 'Puebla', 'Veracruz', 'Golfo', 'bajo demanda', 'Ruta fija', 9800, 23, 700, 450, 300, 48, 34, 52, 48, 93],
            ['Austral', 'CDMX', 'Cancun', 'Sureste', 'bajo demanda', 'Ruta fija + dimension', 35400, 35, 2600, 950, 1500, 96, 86, 112, 96, 87],
        ];
        $insRuta = $pdo->prepare("
            INSERT INTO logistica_rutas
                (linea_id, origen, destino, zona, frecuencia, tipo_cobro, tarifa_fija, costo_km_adicional, recargo_combustible, recargo_maniobras, recargo_zona_extendida, tiempo_estimado_hrs, tiempo_min_hrs, tiempo_max_hrs, sla_hrs, cumplimiento_pct, activo)
            SELECT ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1
            WHERE NOT EXISTS (
                SELECT 1 FROM logistica_rutas
                WHERE linea_id = ? AND origen = ? AND destino = ?
            )
        ");
        foreach ($rutasDemo as $ruta) {
            [$nombreLinea, $origen, $destino, $zona, $frecuencia, $tipoCobro, $tarifa, $km, $combustible, $maniobras, $zonaExt, $estimado, $min, $max, $sla, $cumplimiento] = $ruta;
            if (!isset($ids[$nombreLinea])) continue;
            $lineaId = $ids[$nombreLinea];
            $insRuta->execute([$lineaId, $origen, $destino, $zona, $frecuencia, $tipoCobro, $tarifa, $km, $combustible, $maniobras, $zonaExt, $estimado, $min, $max, $sla, $cumplimiento, $lineaId, $origen, $destino]);
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS reportes_generados (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tipo VARCHAR(80) NOT NULL,
                periodo VARCHAR(20) NOT NULL,
                fecha_inicio DATETIME NOT NULL,
                fecha_fin DATETIME NOT NULL,
                filtros_json TEXT NULL,
                usuario_id INT NULL,
                archivo VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_reportes_tipo (tipo),
                KEY idx_reportes_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        if (!columnExists($pdo, 'reportes_generados', 'archivo')) {
            $pdo->exec("ALTER TABLE reportes_generados ADD COLUMN archivo VARCHAR(255) NULL AFTER usuario_id");
        }

        if (tableExists($pdo, 'pedidos') && tableExists($pdo, 'logistica_rutas')) {
            $rutas = $pdo->query("
                SELECT r.id, r.origen, r.destino, r.zona, l.nombre AS linea
                FROM logistica_rutas r
                JOIN logistica_lineas l ON l.id = r.linea_id
                WHERE r.activo = 1
                ORDER BY r.id ASC
            ")->fetchAll();
            if (!empty($rutas)) {
                $pedidos = $pdo->query("SELECT id FROM pedidos WHERE transporte_linea IS NULL OR transporte_linea = '' OR logistica_ruta_id IS NULL")->fetchAll(PDO::FETCH_COLUMN);
                $up = $pdo->prepare("
                    UPDATE pedidos
                    SET transporte_linea = ?,
                        logistica_ruta_id = ?,
                        ruta_origen = ?,
                        ruta_destino = ?,
                        ruta_zona = ?
                    WHERE id = ?
                ");
                $countRutas = count($rutas);
                foreach ($pedidos as $i => $pedidoId) {
                    $ruta = $rutas[$i % $countRutas];
                    $up->execute([$ruta['linea'], (int)$ruta['id'], $ruta['origen'], $ruta['destino'], $ruta['zona'], (int)$pedidoId]);
                }
            }
        }
    } catch (Throwable $e) {
        // No bloquear la app por una migracion demo opcional.
    }
}

function normalizarCatalogoLineasLogistica(PDO $pdo): void {
    if (!tableExists($pdo, 'logistica_lineas')) {
        return;
    }

    $duplicadas = $pdo->query("
        SELECT nombre, MIN(id) AS keep_id, GROUP_CONCAT(id ORDER BY id) AS ids
        FROM logistica_lineas
        GROUP BY nombre
        HAVING COUNT(*) > 1
    ")->fetchAll();

    foreach ($duplicadas as $dup) {
        $ids = array_map('intval', explode(',', (string)$dup['ids']));
        $keepId = (int)$dup['keep_id'];
        $deleteIds = array_values(array_filter($ids, static fn($id) => $id !== $keepId));
        if (!$deleteIds) {
            continue;
        }
        $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
        foreach (['logistica_unidades', 'logistica_rutas', 'logistica_citas'] as $tabla) {
            if (tableExists($pdo, $tabla) && columnExists($pdo, $tabla, 'linea_id')) {
                $stmt = $pdo->prepare("UPDATE {$tabla} SET linea_id = ? WHERE linea_id IN ({$placeholders})");
                $stmt->execute(array_merge([$keepId], $deleteIds));
            }
        }
        $stmt = $pdo->prepare("DELETE FROM logistica_lineas WHERE id IN ({$placeholders})");
        $stmt->execute($deleteIds);
    }

    if (tableExists($pdo, 'logistica_unidades')) {
        $pdo->exec("
            DELETE u1 FROM logistica_unidades u1
            JOIN logistica_unidades u2
              ON u1.linea_id = u2.linea_id
             AND u1.tipo = u2.tipo
             AND u1.tipo_caja = u2.tipo_caja
             AND u1.id > u2.id
        ");
    }

    if (tableExists($pdo, 'logistica_rutas')) {
        $pdo->exec("
            DELETE r1 FROM logistica_rutas r1
            JOIN logistica_rutas r2
              ON r1.linea_id = r2.linea_id
             AND r1.origen = r2.origen
             AND r1.destino = r2.destino
             AND r1.id > r2.id
        ");
    }

    if (!indexExists($pdo, 'logistica_lineas', 'ux_logistica_lineas_nombre')) {
        $pdo->exec("ALTER TABLE logistica_lineas ADD UNIQUE KEY ux_logistica_lineas_nombre (nombre)");
    }
}
