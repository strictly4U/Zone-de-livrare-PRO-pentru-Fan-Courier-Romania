# Plan Implementare Servicii FAN Courier PRO

## Servicii de Implementat

| # | Serviciu | serviceTypeId | COD serviceTypeId | Restricții | Prioritate |
|---|----------|---------------|-------------------|------------|------------|
| 1 | RedCode | 2 | 9 | Max 5kg | Alta |
| 2 | Express Loco | 5 | 10 | - | Alta |
| 3 | Collect Point OMV | 6 | 11 | Necesită hartă | Medie |
| 4 | Collect Point PayPoint | 7 | 12 | Necesită hartă | Medie |
| 5 | Produse Albe | 13 | 14 | Produse voluminoase | Alta |
| 6 | FANbox | 27 | 28 | Necesită hartă locker | Ultima |

---

## Arhitectură

### Fișiere Noi

```
includes/
├── shipping/
│   ├── class-hgezlpfcr-pro-shipping-base.php      # Clasă abstractă
│   ├── class-hgezlpfcr-pro-shipping-redcode.php
│   ├── class-hgezlpfcr-pro-shipping-express-loco.php
│   ├── class-hgezlpfcr-pro-shipping-collectpoint-omv.php
│   ├── class-hgezlpfcr-pro-shipping-collectpoint-paypoint.php
│   ├── class-hgezlpfcr-pro-shipping-produse-albe.php
│   └── class-hgezlpfcr-pro-shipping-fanbox.php
└── locker/
    ├── class-hgezlpfcr-pro-locker-picker.php      # Handler pentru hartă
    └── js/
        └── locker-map.js                           # JavaScript pentru hartă
```

### Modificări API Client

Adăugare mapping servicii în `class-hgezlpfcr-api-client.php`:

```php
$service_map = [
    'Standard' => 1,
    'RedCode' => 2,
    'Export' => 3,
    'Cont Colector' => 4,
    'Express Loco' => 5,
    'Collect Point OMV' => 6,
    'Collect Point PayPoint' => 7,
    'Produse Albe' => 13,
    'FANbox' => 27,
];

// COD mapping (pentru comenzi cu plata ramburs)
$cod_service_map = [
    1 => 4,   // Standard -> Cont Colector
    2 => 9,   // RedCode COD
    5 => 10,  // Express Loco COD
    6 => 11,  // Collect Point OMV COD
    7 => 12,  // Collect Point PayPoint COD
    13 => 14, // Produse Albe COD
    27 => 28, // FANbox COD
];
```

---

## Detalii per Serviciu

### 1. RedCode (serviceTypeId: 2)

**Descriere:** Livrare expresă în aceeași zi sau a doua zi.

**Restricții:**
- Greutate maximă: 5 kg
- Disponibil doar în anumite zone

**Setări admin:**
- Title (titlu checkout)
- Enable dynamic pricing
- Free shipping minimum
- Fixed cost Bucharest
- Fixed cost Country
- Max weight (default 5kg)

**Cod WooCommerce ID:** `fc_pro_redcode`

---

### 2. Express Loco (serviceTypeId: 5)

**Descriere:** Serviciu de livrare expres.

**Restricții:**
- De verificat disponibilitatea pe zone

**Setări admin:**
- Title
- Enable dynamic pricing
- Free shipping minimum
- Fixed cost Bucharest
- Fixed cost Country

**Cod WooCommerce ID:** `fc_pro_express_loco`

---

### 3. Collect Point OMV (serviceTypeId: 6)

**Descriere:** Ridicare colet din stațiile OMV/Petrom.

**Restricții:**
- Necesită selectare punct din hartă
- Greutate/dimensiuni limitate

**Setări admin:**
- Title
- Enable dynamic pricing
- Free shipping minimum
- Fixed cost

**Funcționalitate specială:**
- Widget hartă pentru selectare punct
- Cookie pentru salvare punct selectat
- Validare la checkout că punctul e selectat

**Cod WooCommerce ID:** `fc_pro_collectpoint_omv`

---

### 4. Collect Point PayPoint (serviceTypeId: 7)

**Descriere:** Ridicare colet din locațiile PayPoint.

**Restricții:**
- Necesită selectare punct din hartă
- Greutate/dimensiuni limitate

**Setări admin:**
- Title
- Enable dynamic pricing
- Free shipping minimum
- Fixed cost

**Funcționalitate specială:**
- Widget hartă pentru selectare punct
- Cookie pentru salvare punct selectat
- Validare la checkout că punctul e selectat

**Cod WooCommerce ID:** `fc_pro_collectpoint_paypoint`

---

### 5. Produse Albe (serviceTypeId: 13)

**Descriere:** Livrare pentru produse voluminoase (electrocasnice mari, mobilă).

**Restricții:**
- Greutate/dimensiuni mari permise
- Tarif special

**Setări admin:**
- Title
- Enable dynamic pricing
- Free shipping minimum
- Fixed cost Bucharest
- Fixed cost Country

**Cod WooCommerce ID:** `fc_pro_produse_albe`

---

### 6. FANbox (serviceTypeId: 27) - ULTIMA

**Descriere:** Livrare în lockere FANbox.

**Restricții:**
- Necesită selectare locker din hartă
- Dimensiuni limitate la compartimentele locker

**Setări admin:**
- Title
- Enable dynamic pricing
- Free shipping minimum
- Fixed cost

**Funcționalitate specială:**
- Widget hartă interactivă pentru selectare locker
- Cookie pentru salvare locker selectat: `fancourier_locker_name`, `fancourier_locker_address`
- Validare la checkout că locker-ul e selectat
- La generare AWB, adresa se înlocuiește cu numele locker-ului

**Librărie externă:** `https://unpkg.com/map-fanbox-points@latest/umd/map-fanbox-points.js`

**Cod WooCommerce ID:** `fc_pro_fanbox`

---

## Ordinea Implementării

1. **RedCode** - Cel mai simplu, similar cu Standard
2. **Express Loco** - Similar cu Standard
3. **Produse Albe** - Similar cu Standard
4. **Collect Point OMV** - Necesită hartă
5. **Collect Point PayPoint** - Necesită hartă (reutilizare cod de la OMV)
6. **FANbox** - Necesită hartă lockere (cea mai complexă)

---

## Pași Implementare per Serviciu

### Pas 1: Clasă Shipping Base (o singură dată)
- Creez `class-hgezlpfcr-pro-shipping-base.php`
- Metodă abstractă pentru serviceTypeId
- Metodă comună pentru calculate_shipping
- Metodă comună pentru is_available

### Pas 2: Pentru fiecare serviciu simplu (RedCode, Express Loco, Produse Albe)
1. Creez clasa specifică extending Base
2. Definesc serviceTypeId, titluri, restricții
3. Înregistrez în WooCommerce
4. Testez

### Pas 3: Pentru servicii cu hartă (Collect Point, FANbox)
1. Creez handler hartă
2. Adaug JavaScript pentru afișare hartă
3. Adaug validare checkout
4. Creez clasele de shipping
5. Testez

---

## Integrare cu Licență PRO

Toate serviciile noi vor fi disponibile **doar cu licență PRO activă**.

În `woo-fancourier-pro.php`:
```php
// Înregistrare servicii PRO
add_filter('woocommerce_shipping_methods', function($methods) {
    if (HGEZLPFCR_Pro_License_Manager::is_license_active()) {
        $methods['fc_pro_redcode'] = 'HGEZLPFCR_Pro_Shipping_RedCode';
        $methods['fc_pro_express_loco'] = 'HGEZLPFCR_Pro_Shipping_ExpressLoco';
        // ... etc
    }
    return $methods;
});
```

---

## Timeline Estimat

| Serviciu | Complexitate | Estimare |
|----------|-------------|----------|
| Base class | Medie | - |
| RedCode | Scăzută | - |
| Express Loco | Scăzută | - |
| Produse Albe | Scăzută | - |
| Collect Point OMV | Medie | - |
| Collect Point PayPoint | Scăzută (reutilizare) | - |
| FANbox | Ridicată | - |

---

## Notă: Actualizare API Client

Înainte de implementare, trebuie actualizat `class-hgezlpfcr-api-client.php` din plugin-ul de bază pentru a suporta toate serviceTypeId-urile.
