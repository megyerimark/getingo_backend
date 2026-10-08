<?php

namespace App\Services;

use App\Models\Project;

class ProjectMentorService
{
    public function __construct(private ProjectValidationService $validator) {}

    public function analyze(Project $project, array $payload, bool $premium): array
    {
        $console = $this->normalizeConsole($payload['console_output'] ?? []);
        $html = (string) ($payload['html_code'] ?? '');
        $css = (string) ($payload['css_code'] ?? '');
        $javascript = (string) ($payload['javascript_code'] ?? '');
        $tier = $premium ? 'pro' : 'standard';
        $suggestions = [];
        $focusTab = $this->focusTab((string) $project->validation_type);
        $tone = 'tip';

        $runtimeError = collect($console)
            ->first(fn (string $line) => str_starts_with(mb_strtoupper($line), 'HIBA:'));

        if (is_string($runtimeError) && $runtimeError !== '') {
            [$summary, $runtimeSuggestion, $runtimeFocus] = $this->runtimeHint($runtimeError);
            $suggestions[] = $runtimeSuggestion;
            $focusTab = $runtimeFocus ?? $focusTab;
            $tone = 'warning';

            if ($premium) {
                $suggestions = array_merge($suggestions, $this->qualityHints($html, $css, $javascript));
            }

            return $this->response($tier, $tone, $summary, $focusTab, $suggestions, $premium);
        }

        if (! filled($project->expected_output)) {
            $summary = 'A futás hibamentesnek tűnik, de ehhez a projekthez még nincs automatikus ellenőrzés beállítva.';
            $suggestions[] = 'Haladj végig a projektleíráson lépésenként, és minden rész után futtasd újra az előnézetet.';

            if ($premium) {
                $suggestions = array_merge($suggestions, $this->qualityHints($html, $css, $javascript));
            }

            return $this->response($tier, 'tip', $summary, $focusTab, $suggestions, $premium);
        }

        $result = $this->validator->validate($project, $payload);
        if ($result['passed']) {
            $summary = $result['trusted']
                ? 'A jelenlegi megoldás megfelel az automatikus ellenőrzésnek.'
                : 'A böngészős visszajelzés alapján a kimenet megfelelőnek tűnik.';
            $suggestions[] = 'Nézd át, tudsz-e egyszerűbb változóneveket, kisebb függvényeket vagy tisztább szerkezetet használni.';

            if ($premium) {
                $suggestions = array_merge($suggestions, $this->qualityHints($html, $css, $javascript));
            }

            return $this->response($tier, 'success', $summary, $focusTab, $suggestions, $premium);
        }

        $type = (string) $project->validation_type;
        $expected = $this->expectedFragments((string) $project->expected_output);
        $missing = str_starts_with($type, 'console_')
            ? $this->missingFragments($type, $expected, $html, $css, $javascript, $console)
            : $this->validator->missingRequirements($project, $payload);
        $missingCount = count($missing);

        if (str_starts_with($type, 'console_')) {
            $summary = $missingCount > 0
                ? 'A program lefut, de a konzolkimenet még nem egyezik teljesen az elvárt eredménnyel.'
                : 'A program lefut, de a konzolkimenet sorrendje vagy formátuma még eltérhet az ellenőrzéstől.';
            $suggestions[] = $this->consoleHint($type, $console, $expected);
            $focusTab = 'javascript';
        } else {
            $summary = $missingCount > 0
                ? "A program lefut, de az ellenőrzés még {$missingCount} szükséges elemet nem talál a megoldásban."
                : 'A program lefut, de a szerveroldali ellenőrzés még eltérést lát a megoldásban.';

            foreach ($missing as $fragment) {
                $suggestions[] = $this->fragmentHint($fragment, $type);
            }

            if ($suggestions === []) {
                $suggestions[] = $this->genericValidationHint($type);
            }
        }

        if ($premium) {
            $suggestions = array_merge($suggestions, $this->qualityHints($html, $css, $javascript));
        }

        return $this->response($tier, 'tip', $summary, $focusTab, $suggestions, $premium);
    }

    private function response(
        string $tier,
        string $tone,
        string $summary,
        string $focusTab,
        array $suggestions,
        bool $premium
    ): array {
        $limit = $premium ? 4 : 1;
        $suggestions = collect($suggestions)
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->unique()
            ->take($limit)
            ->values()
            ->all();

        return [
            'tier' => $tier,
            'tone' => $tone,
            'summary' => $summary,
            'focus_tab' => $focusTab,
            'suggestions' => $suggestions,
        ];
    }

    private function runtimeHint(string $error): array
    {
        $lower = mb_strtolower($error);

        if (str_contains($lower, 'is not defined')) {
            return [
                'A futás egy nem létező JavaScript névnél állt meg.',
                'Ellenőrizd a hibaüzenetben szereplő változó vagy függvény nevét: létrehoztad-e a használata előtt, és mindenhol pontosan ugyanúgy írtad-e.',
                'javascript',
            ];
        }

        if (str_contains($lower, 'assignment to constant variable')) {
            return [
                'A kód egy const változó értékét próbálja később felülírni.',
                'Ha az értéknek változnia kell, nézd meg, valóban const legyen-e, vagy inkább let.',
                'javascript',
            ];
        }

        if (str_contains($lower, 'cannot read properties of null') || str_contains($lower, 'cannot read property')) {
            return [
                'A JavaScript olyan DOM-elemet próbál használni, amelyet nem talált meg.',
                'Hasonlítsd össze a HTML id/class értékét a JavaScript szelektorával, és ellenőrizd, hogy az elem a kód futásakor már létezik-e.',
                'javascript',
            ];
        }

        if (
            str_contains($lower, 'unexpected token') ||
            str_contains($lower, 'unexpected end') ||
            str_contains($lower, 'syntaxerror') ||
            str_contains($lower, 'missing')
        ) {
            return [
                'Szintaktikai hiba miatt a JavaScript nem tudott végigfutni.',
                'A legutóbb módosított résznél párosítsd a zárójeleket, kapcsos zárójeleket és idézőjeleket; különösen a hiba előtti sort nézd meg.',
                'javascript',
            ];
        }

        return [
            'A futás hibát jelzett, ezért először ezt érdemes kijavítani.',
            'Olvasd el a konzol első HIBA sorát, majd keresd meg a benne szereplő műveletet vagy nevet a szerkesztőben. Egyetlen hibát javíts egyszerre, utána futtasd újra.',
            'javascript',
        ];
    }

    private function fragmentHint(string $fragment, string $type): string
    {
        $lower = mb_strtolower($fragment);

        if (str_contains($lower, 'queryselector') || str_contains($lower, 'getelementbyid') || str_contains($lower, 'getelementsby')) {
            return 'A DOM-kiválasztás részét nézd át: az ellenőrzés még nem találja a feladathoz szükséges elem-kijelölést.';
        }
        if (str_contains($lower, 'addeventlistener') || preg_match('/\bon(click|input|change|submit)\b/u', $lower)) {
            return 'Az eseménykezelésnél van eltérés: ellenőrizd, hogy a megfelelő elemhez és eseménytípushoz kötötted-e a műveletet.';
        }
        if (str_contains($lower, 'classlist')) {
            return 'Az osztálykezelést nézd át: a feladat egy class hozzáadását, eltávolítását vagy kapcsolását várja.';
        }
        if (str_contains($lower, 'textcontent') || str_contains($lower, 'innertext') || str_contains($lower, 'innerhtml')) {
            return 'A DOM tartalmának módosításánál van eltérés. Nézd meg, hogy valóban a kiválasztott elem tartalmát frissíted-e.';
        }
        if (str_contains($lower, 'console.log')) {
            return 'A JavaScript kimenetét nézd át: az ellenőrzés még nem találja a szükséges console.log() lépést.';
        }
        if (preg_match('/\b(function|=>)\b/u', $lower)) {
            return 'A feladat függvényes részét nézd át: az ellenőrzés még nem látja a szükséges függvény-szerkezetet.';
        }
        if (preg_match('/\b(for|while|map\(|filter\(|foreach)\b/u', $lower)) {
            return 'Az ismétlés vagy tömbfeldolgozás részénél van eltérés. Nézd meg, hogy minden elemen végigmész-e a feladat szerint.';
        }
        if (preg_match('/\b(async|await|fetch\()\b/u', $lower)) {
            return 'Az aszinkron művelet részét ellenőrizd: a kért adatlekérés vagy várakozás még nem jelenik meg a megfelelő formában.';
        }
        if ($type === 'html_contains') {
            return 'A HTML-ben még hiányzik egy, a feladat által kért elem vagy attribútum. Menj végig a projektleírás HTML-re vonatkozó pontjain.';
        }
        if ($type === 'css_contains') {
            return 'A CSS-ben még hiányzik egy elvárt szabály vagy érték. Ellenőrizd a feladatban megadott megjelenési követelményeket.';
        }
        if ($type === 'javascript_contains') {
            return 'A JavaScriptben még hiányzik egy elvárt művelet. Bontsd a feladatot kisebb lépésekre, és ellenőrizd, melyik lépés nincs még a kódban.';
        }

        return 'Az ellenőrzés még hiányol egy, a projektleírásban szereplő szerkezeti elemet. Hasonlítsd össze a leírás lépéseit a jelenlegi kódoddal.';
    }

    private function consoleHint(string $type, array $actual, array $expected): string
    {
        if ($type === 'console_exact' && count($actual) !== count($expected)) {
            return sprintf(
                'A konzolsorok száma eltér: jelenleg %d sorod van, miközben az ellenőrzés %d sort vár. Nézd meg, nincs-e hiányzó vagy felesleges console.log().',
                count($actual),
                count($expected)
            );
        }

        return 'Ellenőrizd a console.log() sorok sorrendjét, a szóközöket és azt, hogy pontosan a feladatban kért értékeket írod-e ki.';
    }

    private function genericValidationHint(string $type): string
    {
        return match ($type) {
            'html_contains' => 'Menj végig a HTML-követelményeken, különösen az elemeken és attribútumokon.',
            'css_contains' => 'Menj végig a CSS-követelményeken, különösen a szelektorokon és tulajdonságokon.',
            'javascript_contains' => 'Menj végig a JavaScript-lépéseken, és minden lépés után futtasd újra a projektet.',
            'source_contains' => 'Ellenőrizd külön a HTML, CSS és JavaScript részt; valamelyikben még hiányzik egy kért elem.',
            default => 'Hasonlítsd össze a jelenlegi kódot a projektleírás lépéseivel, és egyszerre csak egy eltérést javíts.',
        };
    }

    private function qualityHints(string $html, string $css, string $javascript): array
    {
        $hints = [];

        if (preg_match('/\bvar\s+[A-Za-z_$]/u', $javascript)) {
            $hints[] = 'Pro tipp: ahol lehet, var helyett használj const-ot, a később változó értékekhez pedig let-et.';
        }
        if (substr_count($javascript, 'console.log') > 5) {
            $hints[] = 'Pro tipp: sok debug console.log() maradt a kódban. A kész megoldás előtt érdemes csak a szükséges kimenetet meghagyni.';
        }
        if (preg_match('/<img\b(?![^>]*\balt\s*=)[^>]*>/iu', $html)) {
            $hints[] = 'Pro tipp: van olyan képed, amelyhez nem látok alt attribútumot. Ez akadálymentesség és SEO szempontból is fontos.';
        }
        if (preg_match('/<button\b(?![^>]*\btype\s*=)[^>]*>/iu', $html)) {
            $hints[] = 'Pro tipp: adj type attribútumot a button elemekhez, így űrlapban sem lesz meglepetés a működésük.';
        }
        if (substr_count($css, '!important') > 2) {
            $hints[] = 'Pro tipp: több !important szabályt használsz. Próbáld inkább a szelektorok specifikusságát és a CSS sorrendjét rendbe tenni.';
        }
        if ($this->hasVeryLongLine($html) || $this->hasVeryLongLine($css) || $this->hasVeryLongLine($javascript)) {
            $hints[] = 'Pro tipp: van nagyon hosszú kódsor. Törd több sorra, hogy később gyorsabban átlásd és hibakeresd.';
        }

        if ($hints === []) {
            $hints[] = 'Pro tipp: a szerkezet tisztának tűnik. Következő lépésként adj beszédes neveket a változóknak és tartsd külön az egy feladatot végző kódrészeket.';
        }

        return $hints;
    }

    private function missingFragments(
        string $type,
        array $expected,
        string $html,
        string $css,
        string $javascript,
        array $console
    ): array {
        if ($type === 'console_exact' || $type === 'console_contains') {
            return collect($expected)
                ->reject(fn (string $fragment) => in_array(trim($fragment), $console, true))
                ->values()
                ->all();
        }

        $source = match ($type) {
            'html_contains' => $this->stripHtmlComments($html),
            'css_contains' => $this->stripBlockComments($css),
            'javascript_contains' => $this->stripJavaScriptComments($javascript),
            'source_contains' => implode("\n", [
                $this->stripHtmlComments($html),
                $this->stripBlockComments($css),
                $this->stripJavaScriptComments($javascript),
            ]),
            default => '',
        };

        $normalizedSource = $this->normalizeSource($source);

        return collect($expected)
            ->filter(fn (string $fragment) => ! str_contains($normalizedSource, $this->normalizeSource($fragment)))
            ->values()
            ->all();
    }

    private function focusTab(string $type): string
    {
        return match ($type) {
            'html_contains' => 'html',
            'css_contains' => 'css',
            'javascript_contains', 'console_exact', 'console_contains' => 'javascript',
            default => 'javascript',
        };
    }

    private function expectedFragments(string $expected): array
    {
        return collect(preg_split('/\R/u', $expected) ?: [])
            ->map(fn ($line) => trim((string) $line))
            ->filter(fn (string $line) => $line !== '')
            ->values()
            ->all();
    }

    private function normalizeConsole(array $lines): array
    {
        return collect($lines)
            ->map(fn ($line) => trim((string) $line))
            ->filter(fn (string $line) => $line !== '')
            ->values()
            ->all();
    }

    private function normalizeSource(string $source): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $source));
    }

    private function stripHtmlComments(string $source): string
    {
        return (string) preg_replace('/<!--.*?-->/s', '', $source);
    }

    private function stripBlockComments(string $source): string
    {
        return (string) preg_replace('~/\*.*?\*/~s', '', $source);
    }

    private function stripJavaScriptComments(string $source): string
    {
        $source = $this->stripBlockComments($source);

        return (string) preg_replace('~(^|\s)//[^\r\n]*~m', '$1', $source);
    }

    private function hasVeryLongLine(string $source): bool
    {
        foreach (preg_split('/\R/u', $source) ?: [] as $line) {
            if (mb_strlen((string) $line) > 150) return true;
        }

        return false;
    }
}
