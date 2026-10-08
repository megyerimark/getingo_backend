<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lessons') || !Schema::hasTable('quizzes')) {
            return;
        }

        $lesson = DB::table('lessons')->where('id', 37)->first();

        if (!$lesson || (int) $lesson->category_id !== 6) {
            return;
        }

        $content = <<<'LESSON'
A változók segítségével adatokat tudsz eltárolni a programban, majd később újra felhasználni őket. Szinte minden JavaScript program használ változókat: neveket, számokat, beállításokat, felhasználói adatokat vagy akár egy gomb aktuális állapotát is változóban tárolhatod.

## Mit fogsz megtanulni?
- Mi a változó, és miért van rá szükség.
- Mi a különbség a let, const és var között.
- Hogyan adj beszédes nevet a változóknak.
- Hogyan módosítható egy változó értéke.
- Milyen alapvető adattípusokkal találkozol.
- Mit jelent a blokkhatókör, és miért fontos.
- Melyek a leggyakoribb kezdő hibák.

## Mi az a változó?
A változó egy névvel ellátott hely, amelyhez egy értéket kapcsolunk. A név segítségével később hivatkozhatunk az eltárolt értékre.

```javascript
let pontszam = 10;
console.log(pontszam);
```

Ebben a példában a pontszam a változó neve, a 10 pedig az aktuális értéke.

## let – amikor az érték később változhat
A let kulcsszót akkor használd, ha ugyanahhoz a változóhoz később más értéket szeretnél rendelni.

```javascript
let pontszam = 10;
pontszam = 15;
console.log(pontszam);
```

A második sorban nem hozzuk létre újra a változót. Csak új értéket rendelünk hozzá.

> Jó szabály: ha előre tudod, hogy az érték változni fog, használj let-et.

## const – amikor a változót nem akarod újraértékelni
A const kulcsszóval létrehozott változóhoz később nem rendelhetsz teljesen új értéket.

```javascript
const oldalNev = 'Getingo';
console.log(oldalNev);
```

Ez hibát okozna:

```javascript
const oldalNev = 'Getingo';
oldalNev = 'Másik oldal';
```

A const azonban nem jelenti azt, hogy egy objektum vagy tömb belső tartalma soha nem változhat. Csak magát a változóhoz tartozó hivatkozást nem cserélheted le.

```javascript
const felhasznalo = { nev: 'Anna', pont: 10 };
felhasznalo.pont = 20;
console.log(felhasznalo.pont);
```

> A modern JavaScriptben érdemes alapértelmezetten const-ot használni, és csak akkor let-et választani, ha az értéket ténylegesen újra fogod rendelni.

## var – a régebbi megoldás
Régebbi JavaScript kódokban gyakran találkozol var változókkal.

```javascript
var uzenet = 'Szia!';
```

Új kódban általában a const és let használata ajánlott, mert kiszámíthatóbb blokkhatókört adnak. A var függvényhatókörű, és olyan helyzetekhez vezethet, amelyek kezdőként nehezebben követhetők.

## Változó létrehozása és értékadás
A változó első létrehozását deklarációnak nevezzük.

```javascript
let eletkor;
```

Értéket később is adhatsz neki:

```javascript
let eletkor;
eletkor = 22;
```

A két lépés egyszerre is elvégezhető:

```javascript
let eletkor = 22;
```

## Elnevezési szabályok
A jó változónév segít megérteni a programot.

- Használj beszédes neveket: felhasznaloNev jobb, mint az x.
- A név kezdődhet betűvel, aláhúzással vagy dollárjellel.
- A név nem kezdődhet számmal.
- A kis- és nagybetű különbözik: nev és Nev két külön változó.
- JavaScriptben gyakori a camelCase írásmód: teljesNev, aktualisPontszam.
- Foglalt JavaScript kulcsszót nem használhatsz változónévként.

```javascript
const keresztNev = 'Márk';
let aktualisPontszam = 0;
const MAX_PONTSZAM = 100;
```

Nagybetűs, aláhúzásos nevet általában olyan állandóknál látsz, amelyek a program futása során valódi konstansként viselkednek.

## Alapvető adattípusok
Egy változó többféle típusú értéket tárolhat.

```javascript
const nev = 'Anna';          // string
const eletkor = 24;          // number
const aktiv = true;          // boolean
const uresErtek = null;      // null
let megNincsErtek;           // undefined
const technologiak = ['HTML', 'CSS', 'JavaScript']; // array
const profil = { nev: 'Anna', szint: 3 };            // object
```

A typeof operátorral több érték típusát is ellenőrizheted.

```javascript
console.log(typeof nev);     // string
console.log(typeof eletkor); // number
console.log(typeof aktiv);   // boolean
```

## JavaScript dinamikus típusossága
JavaScriptben egy let változó értékének típusa is megváltozhat.

```javascript
let adat = 10;
adat = 'tíz';
```

Ez megengedett, de valódi projektekben érdemes következetesnek maradni, mert így könnyebb megérteni és hibakeresni a kódot.

## Értékek módosítása
Számokat a korábbi érték felhasználásával is módosíthatsz.

```javascript
let pont = 10;
pont = pont + 5;
pont += 5;
pont++;
```

A += hozzáadja a jobb oldali értéket a meglévő értékhez. A ++ számtípusnál eggyel növeli az értéket.

## Blokkhatókör
A let és const blokkhatókörű. Ha egy kapcsos zárójeles blokkban hozod létre őket, azon kívül nem érhetők el.

```javascript
if (true) {
  const uzenet = 'A blokkon belül látható';
  let szam = 5;
  console.log(uzenet, szam);
}

// Itt az uzenet és a szam már nem érhető el.
```

Ez segít abban, hogy a változók csak ott legyenek használhatók, ahol valóban szükség van rájuk.

## Gyakori hibák
### 1. const értékének újraértékelése
```javascript
const pont = 10;
pont = 20; // Hiba
```
Ha az értéknek változnia kell, használj let-et.

### 2. Változó használata deklaráció előtt
Írd úgy a kódot, hogy a let és const változók deklarációja megelőzze a használatukat.

### 3. Elírt változónév
```javascript
const felhasznaloNev = 'Anna';
console.log(felhasznalonev);
```
A felhasznaloNev és felhasznalonev nem ugyanaz, mert a JavaScript megkülönbözteti a kis- és nagybetűket.

### 4. Túl általános nevek
Az adat, x vagy valami rövid lehet, de nagyobb programban nehéz megmondani, mit jelentenek. Törekedj olyan nevekre, amelyekből kiderül a változó szerepe.

## Mini feladat
Készíts egy egyszerű bemutatkozást változókkal.

1. Hozz létre egy nev nevű const változót.
2. Hozz létre egy eletkor nevű let változót.
3. Növeld az életkort eggyel.
4. Készíts egy mondatot template literal használatával.
5. Írd ki az eredményt a konzolra.

```javascript
const nev = 'Anna';
let eletkor = 24;
eletkor++;

const bemutatkozas = `Szia, ${nev} vagyok, jövőre ${eletkor} éves leszek.`;
console.log(bemutatkozas);
```

## Összefoglalás
- const: ezt válaszd alapértelmezetten, ha nem rendelsz új értéket a változóhoz.
- let: akkor használd, ha az érték később változni fog.
- var: régebbi kódokban találkozhatsz vele, új kódban általában nem szükséges.
- Adj beszédes, következetes neveket a változóknak.
- A let és const blokkhatókörű.
- A változó értékének típusa JavaScriptben dinamikusan változhat.

> A változók megértése alapja a feltételeknek, ciklusoknak, függvényeknek, DOM-kezelésnek és szinte minden további JavaScript témának.
LESSON;

        $html = <<<'HTML'
<div class="demo-card">
  <h2>Változók a gyakorlatban</h2>
  <p>Írd be a neved, majd kattints a gombra.</p>

  <label for="nameInput">Neved</label>
  <input id="nameInput" type="text" placeholder="Például: Anna">

  <button id="greetButton" type="button">Köszönés</button>

  <p id="output" class="output">Itt jelenik meg az eredmény.</p>
  <p class="counter">Kattintások száma: <strong id="clickCount">0</strong></p>
</div>
HTML;

        $css = <<<'CSS'
body {
  margin: 0;
  padding: 32px;
  font-family: Arial, sans-serif;
  background: #f4f7fb;
  color: #17345d;
}

.demo-card {
  max-width: 520px;
  margin: 0 auto;
  padding: 24px;
  border: 1px solid #dce7f4;
  border-radius: 16px;
  background: white;
  box-shadow: 0 12px 30px rgba(20, 60, 110, 0.08);
}

h2 {
  margin-top: 0;
}

label {
  display: block;
  margin-bottom: 6px;
  font-weight: 700;
}

input {
  width: 100%;
  box-sizing: border-box;
  padding: 11px 12px;
  border: 1px solid #cbd9ea;
  border-radius: 9px;
  font: inherit;
}

button {
  margin-top: 12px;
  padding: 10px 16px;
  border: 0;
  border-radius: 9px;
  background: #1677ff;
  color: white;
  font: inherit;
  font-weight: 700;
  cursor: pointer;
}

.output {
  margin-top: 18px;
  padding: 12px;
  border-radius: 9px;
  background: #eef6ff;
}

.counter {
  margin-bottom: 0;
  color: #657a96;
  font-size: 14px;
}
CSS;

        $javascript = <<<'JAVASCRIPT'
const nameInput = document.querySelector('#nameInput');
const greetButton = document.querySelector('#greetButton');
const output = document.querySelector('#output');
const clickCount = document.querySelector('#clickCount');

let clicks = 0;

const appName = 'Getingo';

greetButton.addEventListener('click', () => {
  const name = nameInput.value.trim() || 'Tanuló';

  clicks++;
  output.textContent = `Szia, ${name}! Üdv a ${appName} JavaScript leckéjében.`;
  clickCount.textContent = String(clicks);

  console.log({ name, clicks, appName });
});
JAVASCRIPT;

        $questions = [
            [
                'question' => 'Melyik kulcsszót érdemes alapértelmezetten választani, ha a változóhoz később nem rendelsz új értéket?',
                'option_a' => 'var',
                'option_b' => 'const',
                'option_c' => 'let',
                'option_d' => 'value',
                'correct_answer' => 'b',
            ],
            [
                'question' => 'Mikor célszerű let változót használni?',
                'option_a' => 'Ha az értéket később újra szeretnéd rendelni.',
                'option_b' => 'Csak szövegek tárolására.',
                'option_c' => 'Csak függvényekben.',
                'option_d' => 'Soha, mert a let hibás JavaScript.',
                'correct_answer' => 'a',
            ],
            [
                'question' => 'Mi történik, ha egy const változóhoz később teljesen új értéket próbálsz rendelni?',
                'option_a' => 'A JavaScript automatikusan let-re alakítja.',
                'option_b' => 'Az új érték csendben figyelmen kívül marad.',
                'option_c' => 'Hiba keletkezik.',
                'option_d' => 'Csak számoknál keletkezik hiba.',
                'correct_answer' => 'c',
            ],
            [
                'question' => 'Melyik állítás igaz a let és const változók hatókörére?',
                'option_a' => 'Mindig globálisak.',
                'option_b' => 'Blokkhatókörűek.',
                'option_c' => 'Csak HTML-ben használhatók.',
                'option_d' => 'Nincs hatókörük.',
                'correct_answer' => 'b',
            ],
            [
                'question' => 'Mit ad vissza a typeof 24 kifejezés?',
                'option_a' => 'integer',
                'option_b' => 'string',
                'option_c' => 'number',
                'option_d' => 'float',
                'correct_answer' => 'c',
            ],
            [
                'question' => 'Melyik változónév követi a JavaScriptben gyakori camelCase elnevezést?',
                'option_a' => 'aktualis-pontszam',
                'option_b' => 'Aktualis_Pontszam',
                'option_c' => 'aktualisPontszam',
                'option_d' => '2pontszam',
                'correct_answer' => 'c',
            ],
        ];

        DB::transaction(function () use ($content, $html, $css, $javascript, $questions): void {
            DB::table('lessons')
                ->where('id', 37)
                ->where('category_id', 6)
                ->update([
                    'content' => $content,
                    'example_code' => $javascript,
                    'example_html' => $html,
                    'example_css' => $css,
                    'example_javascript' => $javascript,
                    'updated_at' => now(),
                ]);

            // A meglévő kvízazonosítókat lehetőség szerint megtartjuk, hogy a korábbi
            // quiz_completions rekordok és a megszerzett XP ne vesszen el.
            $existingQuizIds = DB::table('quizzes')
                ->where('lesson_id', 37)
                ->orderBy('id')
                ->pluck('id')
                ->all();

            $now = now();

            foreach ($questions as $index => $question) {
                $payload = [
                    'lesson_id' => 37,
                    ...$question,
                    'updated_at' => $now,
                ];

                if (isset($existingQuizIds[$index])) {
                    DB::table('quizzes')
                        ->where('id', $existingQuizIds[$index])
                        ->update($payload);
                } else {
                    DB::table('quizzes')->insert([
                        ...$payload,
                        'created_at' => $now,
                    ]);
                }
            }

            // Ha korábban hatnál több kérdés tartozott a leckéhez, a fölöslegeseket
            // eltávolítjuk. A legelső hat rekord ID-ja változatlan marad.
            $extraQuizIds = array_slice($existingQuizIds, count($questions));
            if ($extraQuizIds !== []) {
                DB::table('quizzes')->whereIn('id', $extraQuizIds)->delete();
            }
        });
    }

    public function down(): void
    {
        // Tartalmi migráció: rollback esetén nem töröljük a felhasználó által később szerkesztett leckeanyagot.
    }
};
