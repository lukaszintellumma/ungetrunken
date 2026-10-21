<?php
declare(strict_types=1);

/**
 * Buchungstypen und ihre Wirkung auf den Bestand.
 *
 *  zubuchung     Wareneingang           →  + auf Ziel (Zentrallager)
 *  ausgabe       Lieferschein an Theke  →  − von Quelle, + auf Ziel
 *  rueckbuchung  Theke gibt zurück      →  − von Quelle, + auf Ziel
 *  umbuchung     Theke ↔ Theke          →  − von Quelle, + auf Ziel
 *  abbuchung     Verbrauch, Bruch, Schwund → − von Quelle
 *  inventur      Zählung                →  Differenz auf Ziel, setzt Bestand absolut
 */
const BOOKING_TYPES = [
    'zubuchung'    => ['label' => 'Zubuchung (Wareneingang)', 'from' => false, 'to' => true],
    'ausgabe'      => ['label' => 'Ausgabe / Lieferschein',   'from' => true,  'to' => true],
    'rueckbuchung' => ['label' => 'Rückbuchung',              'from' => true,  'to' => true],
    'umbuchung'    => ['label' => 'Umbuchung',                'from' => true,  'to' => true],
    'abbuchung'    => ['label' => 'Abbuchung (Verbrauch)',    'from' => true,  'to' => false],
    'inventur'     => ['label' => 'Inventur (Zählbestand)',   'from' => false, 'to' => true],
];

function type_label(string $t): string {
    return BOOKING_TYPES[$t]['label'] ?? $t;
}

/** Bestand eines Artikels an einer Stelle, in Basiseinheiten */
function stock_of(int $productId, int $outletId): float {
    $v = scalar('SELECT COALESCE(SUM(qty_base),0) FROM stock_movements
                  WHERE product_id = ? AND outlet_id = ?', [$productId, $outletId]);
    return (float)$v;
}

/** Belegnummer vergeben: JJ-Typkürzel-laufend, z. B. 26-AUS-0042 */
function next_beleg(string $type): string {
    $prefixes = [
        'zubuchung' => 'ZU', 'ausgabe' => 'AUS', 'rueckbuchung' => 'RUE',
        'umbuchung' => 'UM', 'abbuchung' => 'AB', 'inventur' => 'INV',
    ];
    $p = $prefixes[$type] ?? 'BUCH';
    $yy = date('y');
    $like = "$yy-$p-%";
    $last = scalar('SELECT beleg FROM bookings WHERE beleg LIKE ? ORDER BY beleg DESC LIMIT 1', [$like]);
    $n = $last ? ((int)substr((string)$last, -4)) + 1 : 1;
    return sprintf('%s-%s-%04d', $yy, $p, $n);
}

/**
 * Entwurf buchen: schreibt die Lagerbewegungen und sperrt den Beleg.
 * Wirft eine Exception, wenn etwas nicht stimmt – dann rollt die Transaktion zurück.
 */
function post_booking(int $bookingId, bool $allowNegative = false): string {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $b = one('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [$bookingId]);
        if (!$b)                        throw new RuntimeException('Beleg nicht gefunden.');
        if ($b['status'] !== 'entwurf') throw new RuntimeException('Dieser Beleg ist bereits gebucht.');

        $spec  = BOOKING_TYPES[$b['type']] ?? null;
        if (!$spec) throw new RuntimeException('Unbekannter Buchungstyp.');
        if ($spec['from'] && !$b['from_outlet_id']) throw new RuntimeException('Für diesen Buchungstyp fehlt die Quelle („Von“).');
        if ($spec['to']   && !$b['to_outlet_id'])   throw new RuntimeException('Für diesen Buchungstyp fehlt das Ziel („Nach“).');
        if ($spec['from'] && $spec['to'] && $b['from_outlet_id'] === $b['to_outlet_id']) {
            throw new RuntimeException('Quelle und Ziel dürfen nicht identisch sein.');
        }

        $items = all('SELECT bi.*, p.name AS pname, p.artikelnr
                        FROM booking_items bi
                        JOIN products p ON p.id = bi.product_id
                       WHERE bi.booking_id = ? AND bi.qty_base <> 0
                       ORDER BY bi.pos', [$bookingId]);
        if (!$items) throw new RuntimeException('Der Beleg enthält keine Positionen mit Menge.');

        $ins = $pdo->prepare('INSERT INTO stock_movements
                (product_id, outlet_id, qty_base, booking_id, booking_item_id)
                VALUES (?,?,?,?,?)');

        foreach ($items as $it) {
            $pid = (int)$it['product_id'];
            $qty = (float)$it['qty_base'];

            if ($b['type'] === 'inventur') {
                // Zählbestand ist absolut: nur die Differenz buchen
                $current = stock_of($pid, (int)$b['to_outlet_id']);
                $diff    = round($qty - $current, 3);
                if (abs($diff) > 0.0001) {
                    $ins->execute([$pid, (int)$b['to_outlet_id'], $diff, $bookingId, (int)$it['id']]);
                }
                continue;
            }

            if ($qty <= 0) {
                throw new RuntimeException("Position {$it['pos']} ({$it['artikelnr']}): Menge muss größer als 0 sein.");
            }

            if ($spec['from']) {
                if (!$allowNegative) {
                    $avail = stock_of($pid, (int)$b['from_outlet_id']);
                    if ($avail + 0.0001 < $qty) {
                        throw new RuntimeException(sprintf(
                            'Position %d – %s: nur %s verfügbar, gebucht werden sollen %s.',
                            $it['pos'], $it['pname'], num($avail, 2), num($qty, 2)
                        ));
                    }
                }
                $ins->execute([$pid, (int)$b['from_outlet_id'], -$qty, $bookingId, (int)$it['id']]);
            }
            if ($spec['to']) {
                $ins->execute([$pid, (int)$b['to_outlet_id'], $qty, $bookingId, (int)$it['id']]);
            }
        }

        $beleg = $b['beleg'] ?: next_beleg($b['type']);
        q('UPDATE bookings SET status = "gebucht", beleg = ?, booked_at = NOW() WHERE id = ?',
          [$beleg, $bookingId]);

        $pdo->commit();
        return $beleg;
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

/** Storno: erzeugt einen Gegenbeleg mit umgekehrten Bewegungen */
function cancel_booking(int $bookingId, int $userId): string {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $b = one('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [$bookingId]);
        if (!$b)                        throw new RuntimeException('Beleg nicht gefunden.');
        if ($b['status'] !== 'gebucht') throw new RuntimeException('Nur gebuchte Belege lassen sich stornieren.');

        $beleg = next_beleg($b['type']);
        q('INSERT INTO bookings (beleg, type, booking_date, from_outlet_id, to_outlet_id,
                                 beleg_text, user_id, status, storno_of_id, booked_at)
           VALUES (?,?,CURDATE(),?,?,?,?,"gebucht",?,NOW())',
          [$beleg, $b['type'], $b['from_outlet_id'], $b['to_outlet_id'],
           'Storno zu ' . $b['beleg'], $userId, $bookingId]);
        $newId = (int)$pdo->lastInsertId();

        // alle Bewegungen des Originals spiegeln
        q('INSERT INTO stock_movements (product_id, outlet_id, qty_base, booking_id)
           SELECT product_id, outlet_id, -qty_base, ? FROM stock_movements WHERE booking_id = ?',
          [$newId, $bookingId]);

        q('UPDATE bookings SET status = "storniert" WHERE id = ?', [$bookingId]);
        $pdo->commit();
        return $beleg;
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

/** Artikel per Artikelnummer oder Barcode finden (für Scanner) */
function find_product(string $code): ?array {
    $code = trim($code);
    if ($code === '') return null;

    $row = one('SELECT p.*, pk.id AS pack_id, pk.factor AS pack_factor, pk.name AS pack_name
                  FROM packagings pk JOIN products p ON p.id = pk.product_id
                 WHERE pk.ean = ? AND p.active = 1 LIMIT 1', [$code]);
    if ($row) return $row;

    return one('SELECT p.*, NULL AS pack_id, NULL AS pack_factor, NULL AS pack_name
                  FROM products p WHERE p.artikelnr = ? AND p.active = 1 LIMIT 1', [$code]);
}
