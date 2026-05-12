<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireGestion();

$usuario = usuarioActual();
$esAdmin = esAdmin();
$esInventario = esInventario();
$nombreRol = nombreRolActual();
$inicioHref = $esInventario ? 'inventario.php?vista=inicio' : 'dashboard.php';
$inicioPendHref = $inicioHref . (strpos($inicioHref, '?') !== false ? '&' : '?') . 'focus_pending=1';
$msg     = '';
$error   = '';

// Hotfix de compatibilidad para hostings donde las tablas de catalogo aun no existen.
try {
    if (!tableExists($pdo, 'especies')) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS especies (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(80) NOT NULL UNIQUE,
                slug VARCHAR(80) NOT NULL UNIQUE,
                descripcion VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    if (!tableExists($pdo, 'subcategorias_catalogo')) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS subcategorias_catalogo (
                id INT AUTO_INCREMENT PRIMARY KEY,
                especie_id INT NOT NULL,
                nombre VARCHAR(80) NOT NULL,
                slug VARCHAR(80) NOT NULL,
                descripcion VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY ux_subcat_especie_slug (especie_id, slug),
                KEY idx_subcat_especie (especie_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    if (tableExists($pdo, 'especies')) {
        $insCatBase = $pdo->prepare("INSERT IGNORE INTO especies (nombre, slug, descripcion) VALUES (?, ?, ?)");
        $insCatBase->execute(['Pollo', 'pollo', 'Categoria base']);
        $insCatBase->execute(['Vaca', 'vaca', 'Categoria base']);
        $insCatBase->execute(['Cerdo', 'cerdo', 'Categoria base']);
    }

    // Compatibilidad de lotes para hostings con migracion incompleta.
    if (!columnExists($pdo, 'productos', 'lote_activo_id')) {
        $pdo->exec("ALTER TABLE productos ADD COLUMN lote_activo_id INT NULL AFTER fecha_ingreso");
    }
    if (!tableExists($pdo, 'producto_lotes')) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS producto_lotes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                producto_id INT NOT NULL,
                lote_numero INT NOT NULL,
                fecha_ingreso DATETIME NOT NULL,
                fecha_caducidad DATETIME NOT NULL,
                unidades INT NOT NULL DEFAULT 0,
                created_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY ux_lote_producto_numero (producto_id, lote_numero),
                KEY idx_lote_producto (producto_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
} catch (Throwable $e) {
    // No romper pantalla de productos por permisos o migracion parcial.
    if ($error === '') {
        $error = 'Catalogo en modo compatibilidad: algunas tablas no estan listas en este servidor.';
    }
}

$categorias = [];
try {
    $categorias = $pdo->query("SELECT id, nombre, slug, descripcion FROM especies ORDER BY nombre ASC")->fetchAll();
} catch (Throwable $e) {
    $categorias = [
        ['id' => 0, 'nombre' => 'Pollo', 'slug' => 'pollo', 'descripcion' => 'Categoria base'],
        ['id' => 0, 'nombre' => 'Vaca', 'slug' => 'vaca', 'descripcion' => 'Categoria base'],
        ['id' => 0, 'nombre' => 'Cerdo', 'slug' => 'cerdo', 'descripcion' => 'Categoria base'],
    ];
}
$subcategoriasCatalogo = [];
try {
    $subcategoriasCatalogo = $pdo->query("
        SELECT especie_id, nombre, slug, descripcion
        FROM subcategorias_catalogo
        ORDER BY nombre ASC
    ")->fetchAll();
} catch (Throwable $e) {
    $subcategoriasCatalogo = [];
}
$subcategoriasPorCategoria = [];
foreach ($subcategoriasCatalogo as $subCat) {
    $catId = (int)$subCat['especie_id'];
    if (!isset($subcategoriasPorCategoria[$catId])) {
        $subcategoriasPorCategoria[$catId] = [];
    }
    $subcategoriasPorCategoria[$catId][] = $subCat;
}
$pendientesCount = (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'pendiente'")->fetchColumn();
$categoriasFijas = ['pollo' => 'Pollo', 'vaca' => 'Vaca', 'cerdo' => 'Cerdo'];
$subcategoriasGlobales = [];
if (tableExists($pdo, 'subcategorias_globales')) {
    try {
        $rowsSub = $pdo->query("SELECT id, nombre, slug FROM subcategorias_globales WHERE activa = 1 ORDER BY nombre ASC")->fetchAll();
        foreach ($rowsSub as $sgr) {
            $slug = strtolower(trim((string)($sgr['slug'] ?? '')));
            if ($slug === '') continue;
            if (in_array($slug, ['pollo', 'vaca', 'cerdo', 'acuacultura'], true)) continue;
            $subcategoriasGlobales[$slug] = [
                'id' => (int)($sgr['id'] ?? 0),
                'nombre' => (string)($sgr['nombre'] ?? ucfirst($slug)),
                'slug' => $slug,
            ];
        }
    } catch (Throwable $e) {
        $subcategoriasGlobales = [];
    }
}
if (empty($subcategoriasGlobales)) {
    $subcategoriasGlobales = [
        'alimento' => ['id' => 0, 'nombre' => 'Alimento', 'slug' => 'alimento'],
        'comida' => ['id' => 0, 'nombre' => 'Comida', 'slug' => 'comida'],
        'proteina' => ['id' => 0, 'nombre' => 'Proteina', 'slug' => 'proteina'],
        'engorda' => ['id' => 0, 'nombre' => 'Engorda', 'slug' => 'engorda'],
        'lacteo' => ['id' => 0, 'nombre' => 'Lacteo', 'slug' => 'lacteo'],
        'suplemento' => ['id' => 0, 'nombre' => 'Suplemento', 'slug' => 'suplemento'],
    ];
}

// â”€â”€ HELPER: guardar imagen â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function subirImagen($fileKey, $idProducto) {
    if (empty($_FILES[$fileKey]['name'])) return null;
    $file = $_FILES[$fileKey];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $permitidos = ['jpg','jpeg','png','webp','gif'];
    if (!in_array($ext, $permitidos)) return 'ext';
    if ($file['size'] > 3 * 1024 * 1024) return 'size'; // 3 MB max
    $dir  = __DIR__ . '/../uploads/productos/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    // Borrar imagen anterior si existe
    $old = glob($dir . "prod_{$idProducto}.*");
    foreach ($old as $f) @unlink($f);
    $dest = $dir . "prod_{$idProducto}.{$ext}";
    if (!move_uploaded_file($file['tmp_name'], $dest)) return 'upload';
    return "prod_{$idProducto}.{$ext}";
}

// â”€â”€ ACCIONES POST â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'set_lote_activo') {
        $idProducto = (int)($_POST['id_producto'] ?? 0);
        $idLote = (int)($_POST['lote_activo_id'] ?? 0);
        if ($idProducto <= 0 || $idLote <= 0) {
            $error = 'Lote activo invalido.';
        } else {
            $st = $pdo->prepare("SELECT id FROM producto_lotes WHERE id = ? AND producto_id = ? LIMIT 1");
            $st->execute([$idLote, $idProducto]);
            if (!$st->fetch()) {
                $error = 'El lote seleccionado no pertenece al producto.';
            } else {
                $pdo->prepare("UPDATE productos SET lote_activo_id = ? WHERE id = ?")->execute([$idLote, $idProducto]);
                $msg = 'Lote activo actualizado.';
            }
        }
    }

    if ($accion === 'generar_lote') {
        $idProducto = (int)($_POST['id_producto'] ?? 0);
        $unidades = (int)($_POST['unidades_lote'] ?? 0);
        $fechaCaducidadLote = trim((string)($_POST['fecha_caducidad_lote'] ?? ''));
        if ($idProducto <= 0 || $unidades <= 0 || $fechaCaducidadLote === '') {
            $error = 'Para generar lote se requieren producto, unidades y fecha de caducidad.';
        } else {
            try {
                $pdo->beginTransaction();
                crearLoteProducto(
                    $pdo,
                    $idProducto,
                    $unidades,
                    $fechaCaducidadLote,
                    fechaMysqlAhora(),
                    (int)($usuario['usuario_id'] ?? 0)
                );
                registrarMovimientoInventario(
                    $pdo,
                    $idProducto,
                    'entrada',
                    $unidades,
                    0,
                    0,
                    'lote',
                    null,
                    'Generacion manual de lote',
                    (int)($usuario['usuario_id'] ?? 0)
                );
                $pdo->commit();
                $msg = 'Nuevo lote generado correctamente.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
    }

    if ($accion === 'crear') {
        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $precio = (float)($_POST['precio'] ?? 0);
        $stock  = (int)($_POST['stock']   ?? 100);
        $categoriaSlug = strtolower(trim((string)($_POST['categoria_slug'] ?? 'pollo')));
        if (!isset($categoriasFijas[$categoriaSlug])) {
            $categoriaSlug = 'pollo';
        }
        $subcategoriaSlug = strtolower(trim((string)($_POST['subcategoria_slug'] ?? 'alimento')));
        if (!isset($subcategoriasGlobales[$subcategoriaSlug])) {
            $subcategoriaSlug = 'alimento';
        }
        $unidadMedida = trim((string)($_POST['unidad_medida'] ?? 'piezas'));
        $fechaCaducidad = trim((string)($_POST['fecha_caducidad'] ?? ''));
        $fechaIngreso = trim((string)($_POST['fecha_ingreso'] ?? ''));
        $clienteAjustable = isset($_POST['cliente_ajustable']) ? 1 : 0;
        $extraLoteUnidades = (int)($_POST['extra_lote_unidades'] ?? 0);
        $extraLoteCaducidad = trim((string)($_POST['extra_lote_caducidad'] ?? ''));
        $extraLoteActivo = isset($_POST['extra_lote_activo']) ? 1 : 0;

        if (!$codigo || !$nombre || $fechaCaducidad === '') {
            $error = 'Codigo, nombre y fecha de caducidad son obligatorios.';
        } elseif (($extraLoteUnidades > 0 && $extraLoteCaducidad === '') || ($extraLoteUnidades <= 0 && $extraLoteCaducidad !== '')) {
            $error = 'Para crear lote extra debes capturar unidades y fecha de caducidad.';
        } else {
            try {
                $pdo->beginTransaction();
                if ($precio < 0) $precio = 0;

                $categoriaId = 0;
                $stCat = $pdo->prepare("SELECT id FROM especies WHERE LOWER(slug)=? OR LOWER(nombre)=? LIMIT 1");
                $stCat->execute([$categoriaSlug, $categoriasFijas[$categoriaSlug] ? strtolower($categoriasFijas[$categoriaSlug]) : $categoriaSlug]);
                $categoriaId = (int)$stCat->fetchColumn();
                if ($categoriaId <= 0 && tableExists($pdo, 'especies')) {
                    $pdo->prepare("INSERT INTO especies (nombre, slug, descripcion) VALUES (?,?,?)")
                        ->execute([$categoriasFijas[$categoriaSlug], $categoriaSlug, 'Categoria fija']);
                    $categoriaId = (int)$pdo->lastInsertId();
                }
                if ($categoriaId <= 0) {
                    throw new RuntimeException('Categoria invalida.');
                }

                $subcategoriaFinal = (string)($subcategoriasGlobales[$subcategoriaSlug]['nombre'] ?? 'Alimento');
                $subcategoriaGlobalId = (int)($subcategoriasGlobales[$subcategoriaSlug]['id'] ?? 0);
                if ($subcategoriaGlobalId <= 0 && tableExists($pdo, 'subcategorias_globales')) {
                    $pdo->prepare("INSERT IGNORE INTO subcategorias_globales (nombre, slug, descripcion, activa) VALUES (?, ?, 'Subcategoria base', 1)")
                        ->execute([$subcategoriaFinal, $subcategoriaSlug]);
                    $stSub = $pdo->prepare("SELECT id FROM subcategorias_globales WHERE slug=? LIMIT 1");
                    $stSub->execute([$subcategoriaSlug]);
                    $subcategoriaGlobalId = (int)$stSub->fetchColumn();
                }
                if (tableExists($pdo, 'subcategorias_catalogo')) {
                    upsertSubcategoriaCatalogo($pdo, $categoriaId, $subcategoriaFinal, null);
                }

                $pdo->prepare("
                    INSERT INTO productos (codigo,nombre,precio,stock,especie_id,subcategoria,unidad_medida,fecha_caducidad,fecha_ingreso,cliente_ajustable)
                    VALUES (?,?,?,?,?,?,?,?,?,?)
                ")->execute([
                    $codigo,
                    $nombre,
                    $precio,
                    0,
                    $categoriaId,
                    $subcategoriaFinal !== '' ? $subcategoriaFinal : 'General',
                    $unidadMedida !== '' ? $unidadMedida : 'piezas',
                    $fechaCaducidad,
                    $fechaIngreso !== '' ? str_replace('T', ' ', $fechaIngreso) . ':00' : date('Y-m-d H:i:s'),
                    $clienteAjustable
                ]);
                $newId  = (int)$pdo->lastInsertId();

                if (columnExists($pdo, 'productos', 'categoria_slug')) {
                    $pdo->prepare("UPDATE productos SET categoria_slug = ? WHERE id = ?")->execute([$categoriaSlug, $newId]);
                }
                if ($subcategoriaGlobalId > 0 && columnExists($pdo, 'productos', 'subcategoria_id')) {
                    $pdo->prepare("UPDATE productos SET subcategoria_id = ? WHERE id = ?")->execute([$subcategoriaGlobalId, $newId]);
                }

                try {
                    crearLoteProducto(
                        $pdo,
                        $newId,
                        max(0, $stock),
                        $fechaCaducidad,
                        $fechaIngreso !== '' ? str_replace('T', ' ', $fechaIngreso) . ':00' : fechaMysqlAhora(),
                        (int)($usuario['usuario_id'] ?? 0)
                    );
                } catch (Throwable $loteErr) {
                    // Fallback fuerte: crear Lote #1 manualmente para no dejar producto sin lotes.
                    $fechaIngTmp = $fechaIngreso !== '' ? str_replace('T', ' ', $fechaIngreso) . ':00' : date('Y-m-d H:i:s');
                    $fechaCadTmp = normalizarFechaMysql($fechaCaducidad, true) ?? (date('Y-m-d') . ' 00:00:00');
                    $pdo->prepare("
                        INSERT INTO producto_lotes (producto_id, lote_numero, fecha_ingreso, fecha_caducidad, unidades, created_by)
                        VALUES (?, 1, ?, ?, ?, ?)
                    ")->execute([
                        $newId,
                        $fechaIngTmp,
                        $fechaCadTmp,
                        max(0, $stock),
                        (int)($usuario['usuario_id'] ?? 0) ?: null
                    ]);
                    $loteFallbackId = (int)$pdo->lastInsertId();
                    $pdo->prepare("UPDATE productos SET lote_activo_id = ?, stock = ? WHERE id = ?")
                        ->execute([$loteFallbackId, max(0, $stock), $newId]);
                }

                // Verificacion final: producto nuevo debe quedar con al menos un lote.
                if (tableExists($pdo, 'producto_lotes')) {
                    $stLotCount = $pdo->prepare("SELECT COUNT(*) FROM producto_lotes WHERE producto_id = ?");
                    $stLotCount->execute([$newId]);
                    $lotCount = (int)$stLotCount->fetchColumn();
                    if ($lotCount === 0) {
                        $fechaIngTmp = $fechaIngreso !== '' ? str_replace('T', ' ', $fechaIngreso) . ':00' : date('Y-m-d H:i:s');
                        $fechaCadTmp = normalizarFechaMysql($fechaCaducidad, true) ?? (date('Y-m-d') . ' 00:00:00');
                        $pdo->prepare("
                            INSERT INTO producto_lotes (producto_id, lote_numero, fecha_ingreso, fecha_caducidad, unidades, created_by)
                            VALUES (?, 1, ?, ?, ?, ?)
                        ")->execute([
                            $newId,
                            $fechaIngTmp,
                            $fechaCadTmp,
                            max(0, $stock),
                            (int)($usuario['usuario_id'] ?? 0) ?: null
                        ]);
                    }
                    $stLotActivo = $pdo->prepare("SELECT id FROM producto_lotes WHERE producto_id = ? ORDER BY lote_numero DESC, id DESC LIMIT 1");
                    $stLotActivo->execute([$newId]);
                    $lotActivoId = (int)$stLotActivo->fetchColumn();
                    if ($lotActivoId > 0) {
                        $pdo->prepare("UPDATE productos SET lote_activo_id = COALESCE(lote_activo_id, ?), stock = ? WHERE id = ?")
                            ->execute([$lotActivoId, max(0, $stock), $newId]);
                    }

                    $stLoteInicial = $pdo->prepare("SELECT id FROM producto_lotes WHERE producto_id = ? ORDER BY lote_numero ASC, id ASC LIMIT 1");
                    $stLoteInicial->execute([$newId]);
                    $loteInicialId = (int)$stLoteInicial->fetchColumn();

                    if ($extraLoteUnidades > 0 && $extraLoteCaducidad !== '') {
                        $loteExtraId = crearLoteProducto(
                            $pdo,
                            $newId,
                            $extraLoteUnidades,
                            $extraLoteCaducidad,
                            $fechaIngreso !== '' ? str_replace('T', ' ', $fechaIngreso) . ':00' : fechaMysqlAhora(),
                            (int)($usuario['usuario_id'] ?? 0)
                        );
                        if (!$extraLoteActivo && $loteInicialId > 0) {
                            $pdo->prepare("UPDATE productos SET lote_activo_id = ? WHERE id = ?")->execute([$loteInicialId, $newId]);
                        } elseif ($extraLoteActivo && $loteExtraId > 0) {
                            $pdo->prepare("UPDATE productos SET lote_activo_id = ? WHERE id = ?")->execute([$loteExtraId, $newId]);
                        }
                    }
                }

                $imagen = subirImagen('imagen', $newId);

                if (is_string($imagen)) {
                    $pdo->prepare("UPDATE productos SET imagen=? WHERE id=?")->execute([$imagen, $newId]);
                } elseif ($imagen === 'ext') {
                    $error .= ' (Formato de imagen no valido. Usa JPG, PNG o WEBP)';
                } elseif ($imagen === 'size') {
                    $error .= ' (La imagen supera 3 MB)';
                }

                $pdo->commit();
                $msg = "Producto \"$nombre\" creado correctamente.";
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = (strpos((string)$e->getMessage(), 'Duplicate') !== false) ? 'El codigo de producto ya existe.' : $e->getMessage();
            }
        }
    }

    if ($accion === 'editar') {
        $id     = (int)$_POST['id'];
        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $precio = (float)($_POST['precio'] ?? 0);
        $stock  = (int)($_POST['stock']   ?? 0);
        $activo = isset($_POST['activo']) ? 1 : 0;
        $fechaCaducidad = trim((string)($_POST['fecha_caducidad'] ?? ''));
        $fechaIngreso = trim((string)($_POST['fecha_ingreso'] ?? ''));
        $clienteAjustable = isset($_POST['cliente_ajustable']) ? 1 : 0;

        if (!$codigo || !$nombre) {
            $error = 'Codigo y nombre son obligatorios.';
        } else {
            try {
                $pdo->beginTransaction();
                $stBase = $pdo->prepare("SELECT especie_id, subcategoria, unidad_medida, stock, lote_activo_id, fecha_ingreso, precio FROM productos WHERE id = ? FOR UPDATE");
                $stBase->execute([$id]);
                $base = $stBase->fetch();
                if (!$base) {
                    throw new RuntimeException('Producto no encontrado.');
                }

                $especieId = (int)($base['especie_id'] ?? 0);
                $subcategoria = (string)($base['subcategoria'] ?? 'General');
                $unidadMedida = (string)($base['unidad_medida'] ?? 'piezas');
                $fechaIngresoGuardada = (string)($base['fecha_ingreso'] ?? '');
                $precioGuardado = isset($base['precio']) ? (float)$base['precio'] : 0.0;
                if ($fechaIngresoGuardada === '') {
                    $fechaIngresoGuardada = date('Y-m-d H:i:s');
                }

                $pdo->prepare("
                    UPDATE productos
                    SET codigo=?,nombre=?,precio=?,stock=?,activo=?,especie_id=?,subcategoria=?,unidad_medida=?,fecha_caducidad=?,fecha_ingreso=?,cliente_ajustable=?
                    WHERE id=?
                ")->execute([
                    $codigo,
                    $nombre,
                    $precioGuardado,
                    $stock,
                    $activo,
                    $especieId > 0 ? $especieId : null,
                    $subcategoria !== '' ? $subcategoria : 'General',
                    $unidadMedida !== '' ? $unidadMedida : 'piezas',
                    $fechaCaducidad !== '' ? $fechaCaducidad : null,
                    $fechaIngresoGuardada,
                    $clienteAjustable,
                    $id
                ]);

                // Ajustar unidades del lote activo para reflejar stock deseado.
                $stockActual = (int)($base['stock'] ?? 0);
                $deltaStock = $stock - $stockActual;
                if ($deltaStock !== 0) {
                    $loteActivoId = (int)($base['lote_activo_id'] ?? 0);
                    if ($loteActivoId <= 0) {
                        $stLote = $pdo->prepare("SELECT id FROM producto_lotes WHERE producto_id = ? ORDER BY fecha_ingreso DESC, lote_numero DESC LIMIT 1");
                        $stLote->execute([$id]);
                        $loteActivoId = (int)$stLote->fetchColumn();
                    }
                    if ($loteActivoId <= 0) {
                        throw new RuntimeException('No existe lote activo para ajustar stock.');
                    }
                    $stUnidadLote = $pdo->prepare("SELECT unidades FROM producto_lotes WHERE id = ? AND producto_id = ? FOR UPDATE");
                    $stUnidadLote->execute([$loteActivoId, $id]);
                    $unidadesActualesLote = (int)$stUnidadLote->fetchColumn();
                    $unidadesNuevasLote = $unidadesActualesLote + $deltaStock;
                    if ($unidadesNuevasLote < 0) {
                        throw new RuntimeException('El ajuste de stock deja unidades negativas en el lote activo.');
                    }
                    $pdo->prepare("UPDATE producto_lotes SET unidades = ? WHERE id = ?")->execute([$unidadesNuevasLote, $loteActivoId]);
                    recalcularStockProductoDesdeLotes($pdo, $id);
                }

                $imagen = subirImagen('imagen', $id);
                if (is_string($imagen)) {
                    $pdo->prepare("UPDATE productos SET imagen=? WHERE id=?")->execute([$imagen, $id]);
                } elseif ($imagen === 'ext') {
                    $error = 'Formato no valido. Usa JPG, PNG o WEBP.';
                } elseif ($imagen === 'size') {
                    $error = 'La imagen supera 3 MB.';
                }

                $pdo->commit();
                if (!$error) $msg = "Producto actualizado correctamente.";
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = (strpos((string)$e->getMessage(), 'Duplicate') !== false) ? 'El codigo ya esta en uso por otro producto.' : $e->getMessage();
            }
        }
    }

    if ($accion === 'eliminar') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE productos SET activo=0 WHERE id=?")->execute([$id]);
        $msg = 'Producto desactivado.';
    }

    if ($accion === 'restaurar') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE productos SET activo=1 WHERE id=?")->execute([$id]);
        $msg = 'Producto reactivado.';
    }

    if ($accion === 'quitar_imagen') {
        $id = (int)$_POST['id'];
        $prod = $pdo->prepare("SELECT imagen FROM productos WHERE id=?");
        $prod->execute([$id]);
        $row = $prod->fetch();
        if ($row['imagen']) {
            @unlink(__DIR__ . '/../uploads/productos/' . $row['imagen']);
            $pdo->prepare("UPDATE productos SET imagen=NULL WHERE id=?")->execute([$id]);
        }
        $msg = 'Imagen eliminada.';
    }
}

// â”€â”€ CARGAR PRODUCTOS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$busqueda     = trim($_GET['q']      ?? '');
$filtroActivo = $_GET['filtro']      ?? 'activos';
$filtroCategoria = strtolower(trim((string)($_GET['categoria'] ?? '')));
if ($filtroCategoria !== '' && !isset($categoriasFijas[$filtroCategoria])) {
    $filtroCategoria = '';
}
$filtroSubcategoria = strtolower(trim((string)($_GET['subcategoria'] ?? '')));
if ($filtroSubcategoria !== '' && !isset($subcategoriasGlobales[$filtroSubcategoria])) {
    $filtroSubcategoria = '';
}
$joinEspecies = tableExists($pdo, 'especies');
$where = ''; $params = [];
if ($busqueda) {
    $where   .= " AND (p.nombre LIKE ? OR p.codigo LIKE ?)";
    $params[] = "%$busqueda%"; $params[] = "%$busqueda%";
}
if ($filtroActivo === 'activos')   $where .= " AND p.activo=1";
if ($filtroActivo === 'inactivos') $where .= " AND p.activo=0";

$joinSubGlobal = tableExists($pdo, 'subcategorias_globales') && columnExists($pdo, 'productos', 'subcategoria_id');
$hasSubGlobalSlugCol = $joinSubGlobal && columnExists($pdo, 'subcategorias_globales', 'slug');
$hasCategoriaSlugCol = columnExists($pdo, 'productos', 'categoria_slug');
$sqlProductos = "
    SELECT
        p.id, p.codigo, p.nombre, p.precio, p.stock, p.activo, p.cliente_ajustable,
        COALESCE(p.imagen,'') AS imagen,
        p.especie_id,
        p.subcategoria,
        p.unidad_medida,
        p.fecha_caducidad,
        p.fecha_ingreso,
        p.lote_activo_id,
        " . ($hasCategoriaSlugCol ? "LOWER(COALESCE(NULLIF(p.categoria_slug,''), 'pollo'))" : "'pollo'") . " AS categoria_slug_ui,
        " . ($joinSubGlobal ? "COALESCE(sg.nombre, p.subcategoria, 'Alimento')" : "COALESCE(p.subcategoria, 'Alimento')") . " AS subcategoria_ui,
        " . (($joinSubGlobal && $hasSubGlobalSlugCol) ? "COALESCE(sg.slug, 'alimento')" : "LOWER(REPLACE(COALESCE(p.subcategoria, 'alimento'),' ','-'))") . " AS subcategoria_slug_ui,
        " . ($joinEspecies ? "COALESCE(e.nombre, 'Sin categoria')" : "'Sin categoria'") . " AS especie_nombre
    FROM productos p
    " . ($joinEspecies ? "LEFT JOIN especies e ON e.id = p.especie_id" : "") . "
    " . ($joinSubGlobal ? "LEFT JOIN subcategorias_globales sg ON sg.id = p.subcategoria_id" : "") . "
    WHERE 1=1 $where
    ORDER BY p.nombre ASC
";
$stmt = $pdo->prepare($sqlProductos);
$stmt->execute($params);
$productos = $stmt->fetchAll();

// Normalizacion y filtrado robusto en PHP para evitar falsos "No hay productos" en datos legacy.
foreach ($productos as $idx => $prodTmp) {
    $cat = strtolower(trim((string)($prodTmp['categoria_slug_ui'] ?? '')));
    if (!isset($categoriasFijas[$cat])) {
        $pid = (int)($prodTmp['id'] ?? 0);
        $slot = $pid > 0 ? ($pid % 3) : ($idx % 3);
        $cat = $slot === 0 ? 'pollo' : ($slot === 1 ? 'vaca' : 'cerdo');
    }
    $productos[$idx]['categoria_slug_ui'] = $cat;

    $subSlug = strtolower(trim((string)($prodTmp['subcategoria_slug_ui'] ?? '')));
    $subSlug = preg_replace('/\s+/u', '-', $subSlug);
    if ($subSlug === '' || !isset($subcategoriasGlobales[$subSlug])) {
        $subNomRaw = trim((string)($prodTmp['subcategoria_ui'] ?? $prodTmp['subcategoria'] ?? 'alimento'));
        $subNom = function_exists('mb_strtolower') ? mb_strtolower($subNomRaw, 'UTF-8') : strtolower($subNomRaw);
        $subNom = preg_replace('/\s+/u', '-', $subNom);
        if ($subNom !== '' && isset($subcategoriasGlobales[$subNom])) {
            $subSlug = $subNom;
        } else {
            $keysSub = array_keys($subcategoriasGlobales);
            $subSlug = !empty($keysSub) ? (string)$keysSub[($idx % count($keysSub))] : 'alimento';
        }
    }
    $productos[$idx]['subcategoria_slug_ui'] = $subSlug;
    if (isset($subcategoriasGlobales[$subSlug]['nombre'])) {
        $productos[$idx]['subcategoria_ui'] = (string)$subcategoriasGlobales[$subSlug]['nombre'];
    }
}

if ($filtroCategoria !== '' || $filtroSubcategoria !== '') {
    $productos = array_values(array_filter($productos, static function($p) use ($filtroCategoria, $filtroSubcategoria) {
        $cat = strtolower(trim((string)($p['categoria_slug_ui'] ?? '')));
        $sub = strtolower(trim((string)($p['subcategoria_slug_ui'] ?? '')));
        if ($filtroCategoria !== '' && $cat !== $filtroCategoria) return false;
        if ($filtroSubcategoria !== '' && $sub !== $filtroSubcategoria) return false;
        return true;
    }));
}

$lotesPorProducto = [];
if (!empty($productos) && tableExists($pdo, 'producto_lotes')) {
    $ids = [];
    foreach ($productos as $prodTmp) {
        $ids[] = (int)($prodTmp['id'] ?? 0);
    }
    $ids = array_values(array_filter($ids, static function($v) { return $v > 0; }));
    if (empty($ids)) {
        $ids = [0];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stLotes = $pdo->prepare("
        SELECT id, producto_id, lote_numero, fecha_ingreso, fecha_caducidad, unidades
        FROM producto_lotes
        WHERE producto_id IN ($placeholders)
        ORDER BY fecha_ingreso DESC, lote_numero DESC
    ");
    $stLotes->execute($ids);
    foreach ($stLotes->fetchAll() as $lt) {
        $pid = (int)$lt['producto_id'];
        if (!isset($lotesPorProducto[$pid])) {
            $lotesPorProducto[$pid] = [];
        }
        $lotesPorProducto[$pid][] = $lt;
    }
}

$totalActivos   = $pdo->query("SELECT COUNT(*) FROM productos WHERE activo=1")->fetchColumn();
$totalInactivos = $pdo->query("SELECT COUNT(*) FROM productos WHERE activo=0")->fetchColumn();
$totalTodos     = $totalActivos + $totalInactivos;

function imgUrl($imagen, $productoId = 0) {
    $imagen = trim((string)$imagen);
    $baseDir = __DIR__ . '/../uploads/productos/';
    if ($imagen !== '') {
        $path = $baseDir . $imagen;
        if (is_file($path)) {
            return "../uploads/productos/$imagen?v=" . filemtime($path);
        }
    }

    $id = (int)$productoId;
    if ($id <= 0 && preg_match('/^prod_(\d+)\.[a-z0-9]+$/i', $imagen, $m)) {
        $id = (int)$m[1];
    }
    if ($id > 0) {
        $candidatos = glob($baseDir . 'prod_' . $id . '.*') ?: [];
        foreach ($candidatos as $cand) {
            if (!is_file($cand)) continue;
            $name = basename($cand);
            return "../uploads/productos/$name?v=" . filemtime($cand);
        }
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusPanel - Productos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/productos.css">
    <style>
.notif-count {
    position: absolute;
    top: -2px;
    right: -2px;
    min-width: 18px;
    height: 18px;
    border-radius: 999px;
    background: #ef4444;
    color: #fff;
    font-size: 11px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 5px;
}
.notif-count.hidden { display: none; }
.panel-noti-dropdown {
    width: 360px;
    max-width: calc(100vw - 24px);
    border: 1px solid var(--border);
    background: #0b1528;
}
.panel-noti-header {
    padding: 10px 12px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #fff;
}
.panel-noti-list {
    max-height: 320px;
    overflow: auto;
    padding: 8px;
    display: grid;
    gap: 8px;
}
.panel-noti-item {
    display: block;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: rgba(255,255,255,.02);
    color: inherit;
    text-decoration: none;
    padding: 10px;
}
.panel-noti-item strong { display: block; font-size: 13px; color: #fff; }
.panel-noti-item small { display: block; color: var(--text-muted); margin-top: 2px; font-size: 11px; }

    /* â•â•â•â• ADMIN PRODUCTOS RESPONSIVE â•â•â•â• */
    @media (max-width: 1024px) {
        .prod-table th:nth-child(4),
        .prod-table td:nth-child(4) { display: none; } /* Ocultar stock */
    }
    @media (max-width: 768px) {
        .prod-page-header { flex-direction: column; align-items: stretch; gap: 12px; }
        .btn-nuevo-prod { justify-content: center; }
        .prod-toolbar { flex-direction: column; gap: 10px; }
        .prod-filtros { flex-wrap: wrap; gap: 6px; }
        .filtro-btn { font-size: 12px; padding: 7px 10px; }
        /* Tabla: ocultar columnas no esenciales */
        .prod-table th:nth-child(4),
        .prod-table td:nth-child(4),
        .prod-table th:nth-child(5),
        .prod-table td:nth-child(5) { display: none; }
        /* Imagen principal mas grande en cards moviles */
        .prod-thumb, .prod-thumb-empty, .prod-thumb-wrap { width: 100% !important; height: 170px !important; }
        .prod-code { font-size: 10px; }
        .prod-name-cell span { font-size: 12px; }
        .prod-price { font-size: 13px; }
        /* Modal full-width */
        .modal-dialog { margin: 8px !important; }
        .modal-two-col { grid-template-columns: 1fr !important; }
    }
    @media (max-width: 480px) {
        /* Solo imagen, nombre, precio y acciones */
        .prod-table th:nth-child(2),
        .prod-table td:nth-child(2) { display: none; } /* Ocultar codigo */
        .prod-table td { padding: 10px 8px; }
        .prod-table th { padding: 10px 8px; }
    }
    @media (max-width: 768px) {
        .content-area { padding: 12px !important; }
        .prod-table-wrap { margin: 0; padding: 0; overflow-x: visible; }
        .prod-table { min-width: 0 !important; width: 100% !important; }
        .prod-toolbar { align-items: stretch; }
        .prod-search-form {
            min-width: 0;
            grid-template-columns: 1fr !important;
            gap: 8px !important;
        }
        .prod-search-form .modal-input,
        .prod-search-form .modal-select { min-width: 0 !important; width: 100% !important; }
        .prod-table th, .prod-table td { font-size: 12px !important; padding: 11px 8px !important; }
        .status-pill { font-size: 11px !important; padding: 4px 8px !important; }
        .btn-table, .action-btn { min-height: 34px; }
        #mobileMenu {
            position: relative;
            z-index: 9999;
            pointer-events: auto !important;
            touch-action: manipulation;
        }
    }
    </style>
</head>
<body>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="logo-icon-sm"><i class="fa-solid fa-hexagon-nodes"></i></div>
            <span class="logo-text-sm">Nexus<strong>Panel</strong></span>
        </div>
    </div>
    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($usuario['nombre'],0,1)) ?></div>
        <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($usuario['nombre']) ?></span>
            <span class="user-role role-admin"><i class="fa-solid fa-boxes-stacked"></i> <?= htmlspecialchars($nombreRol) ?></span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Principal</div>
        <a href="<?= htmlspecialchars($inicioHref) ?>" class="nav-item"><i class="fa-solid fa-gauge-high"></i><span>Inicio</span></a>
        <?php if ($esAdmin): ?><a href="usuarios.php"  class="nav-item"><i class="fa-solid fa-users"></i><span>Usuarios</span></a><?php endif; ?>
        <a href="productos.php" class="nav-item active">
            <i class="fa-solid fa-pills"></i><span>Productos e inventario</span><div class="nav-indicator"></div>
        </a>
        <a href="pedidos.php" class="nav-item"><i class="fa-solid fa-clipboard-check"></i><span>Pedidos</span></a>
        <a href="logistica_masiva.php" class="nav-item"><i class="fa-solid fa-truck-ramp-box"></i><span>Logistica Masiva</span></a>
        <?php if ($esAdmin): ?><?php endif; ?>
        <a href="mensajes.php" class="nav-item"><i class="fa-solid fa-comments"></i><span>Mensajes</span></a>
        <?php if ($esAdmin): ?><div class="nav-section-label">Operaciones</div>
        <a href="mapa.php" class="nav-item"><i class="fa-solid fa-map-location-dot"></i><span>Mapa de Usuarios</span></a><?php endif; ?>
        <div class="nav-section-label">Cuenta</div>
        <a href="../logout.php" class="nav-item nav-logout"><i class="fa-solid fa-right-from-bracket"></i><span>Cerrar sesion</span></a>
    </nav>
</aside>

<main class="main-content">
    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenu"><i class="fa-solid fa-bars"></i></button>
            <div class="breadcrumb-custom">
                <span>NexusPanel</span><i class="fa-solid fa-chevron-right"></i>
                <span class="active">Productos</span>
            </div>
        </div>
        <div class="topbar-right">
            <div class="topbar-date" id="topbarDate"></div>
            <div class="dropdown">
                <button class="topbar-btn" type="button" id="panelNotiBtn" data-bs-toggle="dropdown" aria-expanded="false" title="Notificaciones">
                    <i class="fa-solid fa-bell"></i><span class="notif-count hidden" id="panelNotiCount">0</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end p-0 panel-noti-dropdown" aria-labelledby="panelNotiBtn">
                    <div class="panel-noti-header">
                        <strong>Notificaciones</strong>
                        <button id="btnPanelNotiClear" class="btn btn-sm btn-link p-0" type="button">Limpiar</button>
                    </div>
                    <div class="panel-noti-list" id="panelNotiList">
                        <div style="color:var(--text-muted);font-size:12px;padding:8px;">Sin notificaciones.</div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="content-area">

        <?php if ($msg): ?>
        <div class="alert-nexus alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert-nexus alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Page header -->
        <div class="prod-page-header">
            <div class="prod-page-title">
                <div class="prod-title-icon"><i class="fa-solid fa-capsules"></i></div>
                <div>
                    <h1>Productos de Inventario</h1>
                    <p><?= $totalTodos ?> productos registrados</p>
                </div>
            </div>
            <button class="btn-nuevo-prod" onclick="abrirModalCrear()">
                <i class="fa-solid fa-plus"></i> Nuevo producto
            </button>
        </div>

        <!-- Toolbar -->
        <div class="prod-toolbar">
            <form method="GET" class="prod-search-form" id="searchForm" style="display:grid;grid-template-columns:minmax(280px,1fr) 220px 220px;gap:10px;align-items:center;">
                <div class="prod-search-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($busqueda) ?>"
                           placeholder="Buscar por nombre o codigo..."
                           class="prod-search" id="searchInput">
                    <?php if ($busqueda): ?>
                    <a href="productos.php?filtro=<?= urlencode($filtroActivo) ?>" class="search-clear"><i class="fa-solid fa-xmark"></i></a>
                    <?php endif; ?>
                </div>
                <select class="modal-select" name="categoria" id="filtroCategoriaSelect">
                    <option value="">Todas las categorias</option>
                    <?php foreach ($categoriasFijas as $slugCat => $nombreCat): ?>
                        <option value="<?= htmlspecialchars($slugCat) ?>" <?= $filtroCategoria === $slugCat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($nombreCat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select class="modal-select" name="subcategoria" id="filtroSubcategoriaSelect">
                    <option value="">Todas las subcategorias</option>
                    <?php foreach ($subcategoriasGlobales as $slugSub => $sub): ?>
                        <option value="<?= htmlspecialchars($slugSub) ?>" <?= $filtroSubcategoria === $slugSub ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$sub['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="filtro" value="<?= $filtroActivo ?>">
            </form>
        </div>

        <!-- Tabla -->
        <div class="prod-table-wrap">
            <?php if (empty($productos)): ?>
            <div class="prod-empty">
                <i class="fa-solid fa-capsules"></i>
                <p><?= $busqueda ? "No se encontraron productos con \"".htmlspecialchars($busqueda)."\"" : 'No hay productos.' ?></p>
                <?php if (!$busqueda): ?><button class="btn-nuevo-prod" onclick="abrirModalCrear()"><i class="fa-solid fa-plus"></i> Agregar el primero</button><?php endif; ?>
            </div>
            <?php else: ?>
            <table class="prod-table">
                <thead>
                    <tr>
                        <th style="width:70px;">Imagen</th>
                        <th>Codigo</th>
                        <th>Producto</th>
                        <th>Stock</th>
                        <th>Unidad</th>
                        <th>Categoria</th>
                        <th>Subcategoria</th>
                        <th>Lotes</th>
                        <th>Entrega</th>
                        <th>Ajuste cliente</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($productos as $p):
                    $imgUrl = imgUrl($p['imagen'], (int)$p['id']);
                    $payloadEditar = $p;
                    $payloadEditar['lotes'] = $lotesPorProducto[(int)$p['id']] ?? [];
                ?>
                <tr class="<?= !$p['activo'] ? 'row-inactive' : '' ?>">
                    <td>
                        <div class="prod-thumb-wrap">
                            <?php if ($imgUrl): ?>
                            <img src="<?= $imgUrl ?>" class="prod-thumb" alt="<?= htmlspecialchars($p['nombre']) ?>"
                                 onclick="verImagen('<?= $imgUrl ?>','<?= htmlspecialchars($p['nombre']) ?>')">
                            <?php else: ?>
                            <div class="prod-thumb-empty"><i class="fa-solid fa-flask"></i></div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><span class="prod-code"><?= htmlspecialchars($p['codigo']) ?></span></td>
                    <td>
                        <div class="prod-name-cell">
                            <span><?= htmlspecialchars($p['nombre']) ?></span>
                        </div>
                    </td>
                    <td><span class="prod-stock"><?= $p['stock'] ?></span></td>
                    <td><span class="prod-code"><?= htmlspecialchars($p['unidad_medida'] ?: 'piezas') ?></span></td>
                    <td>
                        <span class="status-pill pill-active">
                            <?= htmlspecialchars($categoriasFijas[strtolower((string)($p['categoria_slug_ui'] ?? 'pollo'))] ?? 'Pollo') ?>
                        </span>
                    </td>
                    <td><span class="prod-code"><?= htmlspecialchars((string)($p['subcategoria_ui'] ?? 'Alimento')) ?></span></td>
                    <?php
                        $cantLotes = isset($lotesPorProducto[(int)$p['id']]) ? count($lotesPorProducto[(int)$p['id']]) : 0;
                        if ($cantLotes === 0 && (int)($p['lote_activo_id'] ?? 0) > 0) $cantLotes = 1;
                    ?>
                    <td><span class="prod-code"><?= (int)$cantLotes ?></span></td>
                    <td>
                        <a class="btn-edit" style="display:inline-flex;align-items:center;justify-content:center;text-decoration:none;" href="pedidos.php?q=<?= urlencode((string)$p['codigo']) ?>" title="Ver entregas/pedidos del producto">
                            <i class="fa-solid fa-truck-fast"></i>
                        </a>
                    </td>
                    <td>
                        <span class="status-pill <?= (int)($p['cliente_ajustable'] ?? 1) === 1 ? 'pill-active' : 'pill-inactive' ?>">
                            <?= (int)($p['cliente_ajustable'] ?? 1) === 1 ? 'Permitido' : 'Bloqueado' ?>
                        </span>
                    </td>
                    <td>
                        <div class="prod-actions">
                            <button class="btn-edit" onclick='abrirModalEditar(<?= json_encode($payloadEditar, JSON_UNESCAPED_UNICODE) ?>)' title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <?php if ($p['activo']): ?>
                            <form method="POST" onsubmit="return confirm('Desactivar este producto?')">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn-delete" title="Desactivar"><i class="fa-solid fa-eye-slash"></i></button>
                            </form>
                            <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="accion" value="restaurar">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn-restore" title="Reactivar"><i class="fa-solid fa-eye"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="prod-count">Mostrando <?= count($productos) ?> producto<?= count($productos)!==1?'s':'' ?></div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- â”€â”€ MODAL CREAR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
<div class="modal fade" id="modalCrear" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content nexus-modal">
            <div class="nexus-modal-header">
                <div class="modal-icon-wrap"><i class="fa-solid fa-plus"></i></div>
                <div><h5>Nuevo Producto</h5><p>Agrega un producto al catalogo de inventario</p></div>
                <button class="modal-close-btn" data-bs-dismiss="modal"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="crear">
                <div class="nexus-modal-body">
                    <div class="modal-two-col">
                        <!-- Col izquierda: campos -->
                        <div class="modal-fields">
                            <div class="nexus-field">
                                <label>Codigo *</label>
                                <div class="field-wrap">
                                    <i class="fa-solid fa-barcode"></i>
                                    <input type="text" name="codigo" class="nexus-input" placeholder="Ej: 101190055" required>
                                </div>
                            </div>
                            <div class="nexus-field">
                                <label>Nombre del producto *</label>
                                <div class="field-wrap">
                                    <i class="fa-solid fa-flask"></i>
                                    <input type="text" name="nombre" class="nexus-input" placeholder="Ej: AVIAX PLUS 25KG MEXICO" required>
                                </div>
                            </div>
                            <div class="nexus-field">
                                <label>Stock inicial</label>
                                <div class="field-wrap">
                                    <i class="fa-solid fa-boxes-stacked"></i>
                                    <input type="number" name="stock" id="crearStockInicial" class="nexus-input" value="100" min="0">
                                </div>
                            </div>
                            <div class="card-panel" style="padding:12px;border-radius:10px;">
                                <div class="panel-header" style="margin-bottom:8px;">
                                    <h3 class="panel-title" style="font-size:13px;">Lotes al crear</h3>
                                </div>
                                <div class="form-row-2">
                                    <div class="nexus-field">
                                        <label>Lote inicial</label>
                                        <div class="field-wrap">
                                            <i class="fa-solid fa-hashtag"></i>
                                            <input type="text" class="nexus-input" value="Lote #1 (automatico)" readonly>
                                        </div>
                                    </div>
                                    <div class="nexus-field">
                                        <label>Unidades lote inicial</label>
                                        <div class="field-wrap">
                                            <i class="fa-solid fa-boxes-stacked"></i>
                                            <input type="number" id="crearLoteInicialUnidades" class="nexus-input" value="100" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="nexus-field">
                                    <label style="display:flex;align-items:center;gap:8px;">
                                        <input type="checkbox" id="toggleLoteExtra">
                                        Agregar un lote extra al guardar
                                    </label>
                                </div>
                                <div id="bloqueLoteExtra" class="hidden">
                                    <div class="form-row-2">
                                        <div class="nexus-field">
                                            <label>Unidades lote extra</label>
                                            <input type="number" name="extra_lote_unidades" id="extraLoteUnidades" class="nexus-input" min="1" placeholder="Ej. 50">
                                        </div>
                                        <div class="nexus-field">
                                            <label>Caducidad lote extra</label>
                                            <input type="date" name="extra_lote_caducidad" id="extraLoteCaducidad" class="nexus-input">
                                        </div>
                                    </div>
                                    <div class="nexus-field">
                                        <label style="display:flex;align-items:center;gap:8px;">
                                            <input type="checkbox" name="extra_lote_activo" value="1">
                                            Dejar lote extra como lote activo
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="nexus-field">
                                <label>Categoria</label>
                                <select name="categoria_slug" id="crearCategoria" class="modal-select" required>
                                    <?php foreach ($categoriasFijas as $slugCat => $nombreCat): ?>
                                        <option value="<?= htmlspecialchars($slugCat) ?>"><?= htmlspecialchars($nombreCat) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-row-2">
                                <div class="nexus-field">
                                    <label>Subcategoria</label>
                                    <select name="subcategoria_slug" id="crearSubcategoria" class="modal-select">
                                        <?php foreach ($subcategoriasGlobales as $slugSub => $subData): ?>
                                            <option value="<?= htmlspecialchars($slugSub) ?>"><?= htmlspecialchars((string)$subData['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="nexus-field">
                                    <label>Unidad</label>
                                    <select name="unidad_medida" class="modal-select">
                                        <option value="piezas">Piezas</option>
                                        <option value="kg">Kg</option>
                                        <option value="cajas">Cajas</option>
                                        <option value="litros">Litros</option>
                                        <option value="bolsas">Bolsas</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row-2">
                                <div class="nexus-field">
                                    <label>Fecha de ingreso</label>
                                    <div class="field-wrap">
                                        <i class="fa-solid fa-calendar-plus"></i>
                                        <input type="datetime-local" name="fecha_ingreso" class="nexus-input" value="<?= date('Y-m-d\TH:i') ?>" readonly>
                                    </div>
                                </div>
                                <div class="nexus-field">
                                    <label>Fecha de caducidad *</label>
                                    <div class="field-wrap">
                                        <i class="fa-solid fa-calendar-xmark"></i>
                                        <input type="date" name="fecha_caducidad" class="nexus-input" required>
                                    </div>
                                </div>
                            </div>
                            <div class="nexus-field">
                                <label style="display:flex;align-items:center;gap:8px;">
                                    <input type="checkbox" name="cliente_ajustable" value="1" checked>
                                    Permitir ajustes del cliente en pedido
                                </label>
                            </div>
                        </div>
                        <!-- Col derecha: imagen -->
                        <div class="modal-img-col">
                            <label class="img-upload-label">Imagen del producto</label>
                            <div class="img-drop-zone" id="dropZoneCrear">
                                <div class="img-drop-placeholder" id="placeholderCrear">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                    <p>Clic o arrastra una imagen</p>
                                    <span>JPG, PNG, WEBP - max 3 MB</span>
                                </div>
                                <img id="previewCrear" class="img-preview hidden" alt="preview" style="pointer-events:none;">
                            </div>
                            <input type="file" name="imagen" id="imgInputCrear" accept="image/*" class="hidden"
                                   onchange="previewImg(this,'previewCrear','placeholderCrear')">
                            <button type="button" class="btn-quitar-img hidden" id="btnQuitarCrear"
                                    onclick="quitarPreview('imgInputCrear','previewCrear','placeholderCrear','btnQuitarCrear')">
                                <i class="fa-solid fa-xmark"></i> Quitar imagen
                            </button>
                        </div>
                    </div>
                </div>
                <div class="nexus-modal-footer">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-modal-confirm"><i class="fa-solid fa-plus"></i> Crear producto</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- â”€â”€ MODAL EDITAR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content nexus-modal">
            <div class="nexus-modal-header">
                <div class="modal-icon-wrap" style="background:rgba(124,58,237,0.15);color:var(--accent)"><i class="fa-solid fa-pen"></i></div>
                <div><h5>Editar Producto</h5><p>Modifica los datos del producto</p></div>
                <button class="modal-close-btn" data-bs-dismiss="modal"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="id"     id="editId">
                <div class="nexus-modal-body">
                    <div class="modal-two-col">
                        <!-- Campos -->
                        <div class="modal-fields">
                            <div class="nexus-field">
                                <label>Codigo *</label>
                                <div class="field-wrap">
                                    <i class="fa-solid fa-barcode"></i>
                                    <input type="text" name="codigo" id="editCodigo" class="nexus-input" required>
                                </div>
                            </div>
                            <div class="nexus-field">
                                <label>Nombre del producto *</label>
                                <div class="field-wrap">
                                    <i class="fa-solid fa-flask"></i>
                                    <input type="text" name="nombre" id="editNombre" class="nexus-input" required>
                                </div>
                            </div>
                            <div class="form-row-2">
                                <div class="nexus-field">
                                    <label>Stock</label>
                                    <div class="field-wrap">
                                        <i class="fa-solid fa-boxes-stacked"></i>
                                        <input type="number" name="stock" id="editStock" class="nexus-input" min="0">
                                    </div>
                                </div>
                                <div class="nexus-field">
                                    <label>Lote activo</label>
                                    <select class="modal-select" id="editLoteActivoId" name="lote_activo_id" disabled>
                                        <option value="">Sin lote</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row-2">
                                <div class="nexus-field">
                                    <label>Categoria</label>
                                    <select name="especie_id" id="editEspecieId" class="modal-select" required disabled>
                                        <?php foreach ($categoriasFijas as $slugCat => $nombreCat): ?>
                                            <option value="<?= htmlspecialchars($slugCat) ?>"><?= htmlspecialchars($nombreCat) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="nexus-field">
                                    <label>Subcategoria</label>
                                    <select name="subcategoria" id="editSubcategoria" class="modal-select" disabled>
                                        <?php foreach ($subcategoriasGlobales as $slugSub => $subData): ?>
                                            <option value="<?= htmlspecialchars((string)$subData['nombre']) ?>"><?= htmlspecialchars((string)$subData['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row-2">
                                <div class="nexus-field">
                                    <label>Unidad</label>
                                    <select name="unidad_medida" id="editUnidadMedida" class="modal-select" disabled>
                                        <option value="piezas">Piezas</option>
                                        <option value="kg">Kg</option>
                                        <option value="cajas">Cajas</option>
                                        <option value="litros">Litros</option>
                                        <option value="bolsas">Bolsas</option>
                                    </select>
                                </div>
                                <div class="nexus-field">
                                    <label style="display:flex;align-items:center;gap:8px;">
                                        <input type="checkbox" name="cliente_ajustable" id="editClienteAjustable" value="1">
                                        Permitir ajustes del cliente
                                    </label>
                                </div>
                            </div>
                            <div class="form-row-2">
                                <div class="nexus-field">
                                    <label>Fecha de ingreso</label>
                                    <div class="field-wrap">
                                        <i class="fa-solid fa-calendar-plus"></i>
                                        <input type="datetime-local" name="fecha_ingreso" id="editFechaIngreso" class="nexus-input" readonly>
                                    </div>
                                </div>
                                <div class="nexus-field">
                                    <label>Fecha de caducidad</label>
                                    <div class="field-wrap">
                                        <i class="fa-solid fa-calendar-xmark"></i>
                                        <input type="date" name="fecha_caducidad" id="editFechaCaducidad" class="nexus-input">
                                    </div>
                                </div>
                            </div>
                            <div class="card-panel" style="padding:12px;border-radius:10px;">
                                <div class="panel-header" style="margin-bottom:8px;">
                                    <h3 class="panel-title" style="font-size:13px;">Lotes del producto</h3>
                                </div>
                                <div class="table-wrapper">
                                    <table class="table-mini" id="editLotesTable">
                                        <thead><tr><th>Lote #</th><th>Fecha ingreso</th><th>Fecha caducidad</th><th>Unidades</th></tr></thead>
                                        <tbody><tr><td colspan="4" style="color:var(--text-dim);">Sin lotes</td></tr></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <!-- Imagen -->
                        <div class="modal-img-col">
                            <label class="img-upload-label">Visibilidad</label>
                            <div class="toggle-wrap" style="opacity:.9;">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="activo" id="editActivo" value="1">
                                    <span class="toggle-slider"></span>
                                </label>
                                <span id="editActivoLabel">Visible</span>
                            </div>
                            <label class="img-upload-label">Imagen del producto</label>
                            <div class="img-drop-zone" id="dropZoneEditar">
                                <div class="img-drop-placeholder" id="placeholderEditar">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                    <p>Clic o arrastra una imagen</p>
                                    <span>JPG, PNG, WEBP - max 3 MB</span>
                                </div>
                                <img id="previewEditar" class="img-preview hidden" alt="preview" style="pointer-events:none;">
                            </div>
                            <input type="file" name="imagen" id="imgInputEditar" accept="image/*" class="hidden"
                                   onchange="previewImg(this,'previewEditar','placeholderEditar')">
                            <div class="img-edit-btns">
                                <button type="button" class="btn-quitar-img hidden" id="btnQuitarEditar"
                                        onclick="quitarPreview('imgInputEditar','previewEditar','placeholderEditar','btnQuitarEditar')">
                                    <i class="fa-solid fa-xmark"></i> Quitar nueva imagen
                                </button>
                                <form method="POST" style="display:grid;gap:6px;">
                                    <input type="hidden" name="accion" value="set_lote_activo">
                                    <input type="hidden" name="id_producto" id="setLoteProductoId">
                                    <select name="lote_activo_id" id="setLoteActivoSelect" class="modal-select">
                                        <option value="">Selecciona lote activo</option>
                                    </select>
                                    <button type="submit" class="btn-modal-cancel" style="padding:8px 10px;">Aplicar lote activo</button>
                                </form>
                                <form method="POST" style="display:grid;gap:6px;">
                                    <input type="hidden" name="accion" value="generar_lote">
                                    <input type="hidden" name="id_producto" id="newLoteProductoId">
                                    <input type="number" name="unidades_lote" class="nexus-input" min="1" placeholder="Unidades nuevo lote" required>
                                    <input type="date" name="fecha_caducidad_lote" class="nexus-input" required>
                                    <button type="submit" class="btn-modal-confirm" style="padding:8px 10px;justify-content:center;">
                                        <i class="fa-solid fa-layer-group"></i> Generar nuevo lote
                                    </button>
                                </form>
                                <!-- Boton para eliminar imagen guardada en BD -->
                                <form method="POST" id="formQuitarImgBD" style="display:none;">
                                    <input type="hidden" name="accion" value="quitar_imagen">
                                    <input type="hidden" name="id" id="quitarImgId">
                                    <button type="submit" class="btn-quitar-img-bd" onclick="return confirm('Eliminar la imagen actual?')">
                                        <i class="fa-solid fa-trash"></i> Eliminar imagen actual
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="nexus-modal-footer">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-modal-confirm" style="background:linear-gradient(135deg,var(--accent),#6d28d9)">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- â”€â”€ MODAL VER IMAGEN â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
<div class="modal fade" id="modalVerImg" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content nexus-modal" style="background:rgba(6,10,18,0.97)!important;">
            <div style="position:relative;padding:8px;">
                <button class="modal-close-btn" data-bs-dismiss="modal"
                        style="position:absolute;top:12px;right:12px;z-index:10;background:rgba(0,0,0,0.5);border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <img id="verImgSrc" src="" alt="" style="width:100%;border-radius:14px;display:block;">
                <p id="verImgNombre" style="text-align:center;padding:12px 0 6px;font-size:13px;color:var(--text-muted);"></p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const PANEL_USER_ID = <?= (int)($usuario['usuario_id'] ?? 0) ?>;
let panelNotiCache = [];
function safe(s){ return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;'); }
function panelNotiSeenKey(){ return `panel_noti_seen_${PANEL_USER_ID}`; }
function panelNotiSeenGet(){ try{ return new Set(JSON.parse(localStorage.getItem(panelNotiSeenKey()) || '[]')); }catch(_){ return new Set(); } }
function panelNotiSeenSet(setObj){ try{ localStorage.setItem(panelNotiSeenKey(), JSON.stringify(Array.from(setObj).slice(-600))); }catch(_){} }
function renderPanelNotis(rows){
    const list = document.getElementById('panelNotiList');
    const badge = document.getElementById('panelNotiCount');
    if(!list || !badge) return;
    panelNotiCache = Array.isArray(rows) ? rows : [];
    const seen = panelNotiSeenGet();
    const unseen = panelNotiCache.filter(r => !seen.has(String(r.uid || '')));
    badge.textContent = String(unseen.length);
    badge.classList.toggle('hidden', unseen.length < 1);
    if (!unseen.length){
        list.innerHTML = '<div style="color:var(--text-muted);font-size:12px;padding:8px;">Sin notificaciones.</div>';
        return;
    }
    list.innerHTML = unseen.map(r => `
        <a href="${safe(r.goto || '#')}" class="panel-noti-item" style="${seen.has(String(r.uid||'')) ? '' : 'border-color:rgba(0,212,255,.55);box-shadow:0 0 0 1px rgba(0,212,255,.18) inset;'}">
            <strong>${safe(r.title || 'Notificacion')}</strong>
            <small>${safe(r.from || 'Sistema')}</small>
            <div style="font-size:12px;color:var(--text-light);margin-top:4px;">${safe(r.body || '')}</div>
            <small>${safe(r.ts || '')}</small>
        </a>
    `).join('');
}
async function pollPanelNotis(){
    try{
        const res = await fetch('../api/pedido.php?action=panel_notificaciones&limit=80', { cache:'no-store' });
        if(!res.ok) return;
        const rows = await res.json();
        if(!Array.isArray(rows)) return;
        renderPanelNotis(rows);
    }catch(_){}
}
function markPanelNotisRead(){
    const seen = panelNotiSeenGet();
    (panelNotiCache || []).forEach(r => seen.add(String(r.uid || '')));
    panelNotiSeenSet(seen);
    renderPanelNotis(panelNotiCache);
}
function initPanelNotis(){
    const clearBtn = document.getElementById('btnPanelNotiClear');
    const dropBtn = document.getElementById('panelNotiBtn');
    clearBtn?.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); markPanelNotisRead(); });
    dropBtn?.addEventListener('show.bs.dropdown', () => markPanelNotisRead());
    pollPanelNotis();
    setInterval(pollPanelNotis, 7000);
}

const SUBCATS_MAP = <?= json_encode($subcategoriasPorCategoria, JSON_UNESCAPED_UNICODE) ?>;
// â”€â”€ ABRIR FILE INPUT desde cualquier parte del drop zone â”€â”€â”€
// Usamos event listener en lugar de onclick para evitar que
// la imagen interna bloquee el clic
document.addEventListener('DOMContentLoaded', () => {

    // Drop zone CREAR
    const dzCrear = document.getElementById('dropZoneCrear');
    dzCrear?.addEventListener('click', () => document.getElementById('imgInputCrear').click());

    // Drop zone EDITAR - tambien se inicializa aqui
    const dzEditar = document.getElementById('dropZoneEditar');
    dzEditar?.addEventListener('click', () => document.getElementById('imgInputEditar').click());

    // Drag & drop para ambas zonas
    [['dropZoneCrear','imgInputCrear'],['dropZoneEditar','imgInputEditar']].forEach(([zoneId, inputId]) => {
        const zone = document.getElementById(zoneId); if (!zone) return;
        zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('drag-over'); });
        zone.addEventListener('dragleave', ()  => zone.classList.remove('drag-over'));
        zone.addEventListener('drop', e => {
            e.preventDefault(); zone.classList.remove('drag-over');
            const input = document.getElementById(inputId);
            const dt = new DataTransfer();
            dt.items.add(e.dataTransfer.files[0]);
            input.files = dt.files;
            input.dispatchEvent(new Event('change'));
        });
    });

    const catCrear = document.getElementById('crearCategoria');
    const subCrear = document.getElementById('crearSubcategoria');
    if (catCrear && !catCrear.value) catCrear.value = 'pollo';
    if (subCrear && !subCrear.value) subCrear.selectedIndex = 0;

    document.getElementById('filtroCategoriaSelect')?.addEventListener('change', () => {
        document.getElementById('searchForm')?.submit();
    });
    document.getElementById('filtroSubcategoriaSelect')?.addEventListener('change', () => {
        document.getElementById('searchForm')?.submit();
    });

    const stockInput = document.getElementById('crearStockInicial');
    const loteInicialInput = document.getElementById('crearLoteInicialUnidades');
    if (stockInput && loteInicialInput) {
        const syncStock = () => {
            const v = Number(stockInput.value || 0);
            loteInicialInput.value = String(v >= 0 ? v : 0);
        };
        stockInput.addEventListener('input', syncStock);
        syncStock();
    }

    const toggleExtra = document.getElementById('toggleLoteExtra');
    const bloqueExtra = document.getElementById('bloqueLoteExtra');
    const extraUnidades = document.getElementById('extraLoteUnidades');
    const extraCad = document.getElementById('extraLoteCaducidad');
    if (toggleExtra && bloqueExtra) {
        const refreshExtra = () => {
            const on = !!toggleExtra.checked;
            bloqueExtra.classList.toggle('hidden', !on);
            if (extraUnidades) extraUnidades.required = on;
            if (extraCad) extraCad.required = on;
            if (!on) {
                if (extraUnidades) extraUnidades.value = '';
                if (extraCad) extraCad.value = '';
            }
        };
        toggleExtra.addEventListener('change', refreshExtra);
        refreshExtra();
    }
});

// â”€â”€ PREVIEW â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function previewImg(input, previewId, placeholderId) {
    if (!input.files?.[0]) return;
    const file = input.files[0];
    if (file.size > 3 * 1024 * 1024) { alert('Maximo 3 MB'); input.value = ''; return; }
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById(previewId);
        img.src = e.target.result;
        img.classList.remove('hidden');
        document.getElementById(placeholderId).classList.add('hidden');
        const btnId = previewId === 'previewCrear' ? 'btnQuitarCrear' : 'btnQuitarEditar';
        document.getElementById(btnId)?.classList.remove('hidden');
    };
    reader.readAsDataURL(file);
}

function quitarPreview(inputId, previewId, placeholderId, btnId) {
    document.getElementById(inputId).value = '';
    const img = document.getElementById(previewId);
    img.src = ''; img.classList.add('hidden');
    document.getElementById(placeholderId).classList.remove('hidden');
    document.getElementById(btnId)?.classList.add('hidden');
}

// â”€â”€ MODAL CREAR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function abrirModalCrear() {
    quitarPreview('imgInputCrear','previewCrear','placeholderCrear','btnQuitarCrear');
    new bootstrap.Modal(document.getElementById('modalCrear')).show();
}

// â”€â”€ MODAL EDITAR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function abrirModalEditar(p) {
    // Campos de texto
    document.getElementById('editId').value     = p.id;
    document.getElementById('editCodigo').value = p.codigo;
    document.getElementById('editNombre').value = p.nombre;
    document.getElementById('editStock').value  = p.stock;
    document.getElementById('editEspecieId').value = p.categoria_slug_ui || 'pollo';
    document.getElementById('editSubcategoria').value = p.subcategoria_ui || p.subcategoria || 'Alimento';
    document.getElementById('editUnidadMedida').value = p.unidad_medida || 'piezas';
    document.getElementById('editFechaCaducidad').value = p.fecha_caducidad || '';
    document.getElementById('editFechaIngreso').value = p.fecha_ingreso ? String(p.fecha_ingreso).replace(' ', 'T').slice(0, 16) : '';
    document.getElementById('editClienteAjustable').checked = Number(p.cliente_ajustable || 0) === 1;
    document.getElementById('setLoteProductoId').value = p.id;
    document.getElementById('newLoteProductoId').value = p.id;
    const chk = document.getElementById('editActivo');
    chk.checked = (p.activo == 1);
    document.getElementById('editActivoLabel').textContent = chk.checked ? 'Visible' : 'Oculto';
    chk.onchange = () => document.getElementById('editActivoLabel').textContent = chk.checked ? 'Visible' : 'Oculto';

    const lotes = Array.isArray(p.lotes) ? p.lotes : [];
    const setLoteSelect = document.getElementById('setLoteActivoSelect');
    const lotesDisplaySelect = document.getElementById('editLoteActivoId');
    setLoteSelect.innerHTML = '<option value="">Selecciona lote activo</option>';
    lotesDisplaySelect.innerHTML = '<option value="">Sin lote</option>';
    lotes.forEach((lote) => {
        const txt = `Lote #${lote.lote_numero} - ${lote.unidades} u - cad ${lote.fecha_caducidad}`;
        const optA = document.createElement('option');
        optA.value = lote.id;
        optA.textContent = txt;
        if (Number(lote.id) === Number(p.lote_activo_id || 0)) optA.selected = true;
        setLoteSelect.appendChild(optA);

        const optB = document.createElement('option');
        optB.value = lote.id;
        optB.textContent = txt;
        if (Number(lote.id) === Number(p.lote_activo_id || 0)) optB.selected = true;
        lotesDisplaySelect.appendChild(optB);
    });

    const tbody = document.querySelector('#editLotesTable tbody');
    if (tbody) {
        if (!lotes.length) {
            tbody.innerHTML = '<tr><td colspan="4" style="color:var(--text-dim);">Sin lotes</td></tr>';
        } else {
            tbody.innerHTML = lotes.map((l) => `
                <tr>
                    <td>#${l.lote_numero}</td>
                    <td>${String(l.fecha_ingreso || '').slice(0, 16).replace('T', ' ')}</td>
                    <td>${l.fecha_caducidad || '-'}</td>
                    <td>${l.unidades}</td>
                </tr>
            `).join('');
        }
    }

    // Resetear input de archivo
    document.getElementById('imgInputEditar').value = '';
    document.getElementById('btnQuitarEditar')?.classList.add('hidden');

    // Imagen guardada en BD
    const prev    = document.getElementById('previewEditar');
    const ph      = document.getElementById('placeholderEditar');
    const formQI  = document.getElementById('formQuitarImgBD');
    const quitarId= document.getElementById('quitarImgId');
    if (quitarId) quitarId.value = p.id;

    const tieneImg = p.imagen && String(p.imagen).trim() !== '' && p.imagen !== 'null';
    if (tieneImg) {
        prev.src = '../uploads/productos/' + p.imagen + '?v=' + Date.now();
        prev.classList.remove('hidden');
        ph.classList.add('hidden');
        if (formQI) formQI.style.display = 'block';
    } else {
        prev.src = '';
        prev.classList.add('hidden');
        ph.classList.remove('hidden');
        if (formQI) formQI.style.display = 'none';
    }

    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}

// â”€â”€ VER IMAGEN AMPLIADA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function verImagen(src, nombre) {
    document.getElementById('verImgSrc').src = src;
    document.getElementById('verImgNombre').textContent = nombre;
    new bootstrap.Modal(document.getElementById('modalVerImg')).show();
}

// BUSQUEDA
let searchTimer;
document.getElementById('searchInput')?.addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => document.getElementById('searchForm').submit(), 500);
});

// â”€â”€ CLOCK â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function updateClock() {
    const el = document.getElementById('topbarDate'); if (!el) return;
    const now = new Date();
    el.textContent = now.toLocaleDateString('es-MX',{weekday:'short',day:'2-digit',month:'short'})
        + ' - ' + now.toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'});
}
updateClock(); setInterval(updateClock, 1000);
(() => {
    const btn = document.getElementById('mobileMenu');
    const sidebar = document.getElementById('sidebar') || document.querySelector('.sidebar');
    if (!btn || !sidebar) return;
    const toggle = (e) => {
        e.preventDefault();
        e.stopPropagation();
        const open = !sidebar.classList.contains('open');
        sidebar.classList.toggle('open', open);
        document.body.classList.toggle('sidebar-open', open);
    };
    btn.addEventListener('touchstart', (e) => { e.preventDefault(); }, { passive: false });
    btn.addEventListener('click', toggle);
})();

initPanelNotis();
</script>
</body>
</html>










