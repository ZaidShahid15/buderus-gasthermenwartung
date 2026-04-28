<?php

namespace App\Support;

class SeoSupport
{
    public static function injectPageEnhancements(string $view, string $html): string
    {
        $html = self::replaceBrokenImageUrls($html);
        $html = self::injectFooterLinkStyles($html);

        return match ($view) {
            'home' => self::injectHomeEnhancements($html),
            'datenschutz' => self::injectDatenschutzEnhancements($html),
            'elementor-hf-19', 'elementor-hf-footer' => self::injectHelperPageEnhancements($html),
            default => $html,
        };
    }

    public static function injectHomeEnhancements(string $html): string
    {
        $html = str_replace(
            '<title>Buderus Gasthermenwartung Wien &amp; N&Ouml; | 24h Notdienst f&uuml;r Gas, Wasser &amp; Heizung</title>',
            '<title>Buderus Gasthermenwartung Wien | 24h Notdienst &amp; Service</title>',
            $html
        );

        $html = str_replace(
            '<meta name="description" content="24 Stunden Buderus Gasthermenwartung in Wien &amp; Nieder&ouml;sterreich. Schnelle Thermenreparatur, Wartung &amp; Heizungsservice durch erfahrene Techniker. Jetzt anrufen: +43 1 442 0407.">',
            '<meta name="description" content="Buderus Gasthermenwartung in Wien, Nieder&ouml;sterreich und Burgenland: Wartung, Reparatur &amp; Thermentausch vom Meisterbetrieb. 24h Notdienst: +43 1 4420408.">',
            $html
        );

        $html = str_replace(
            '<meta property="og:title" content="Buderus Gasthermenwartung Wien &amp; N&Ouml; | 24h Notdienst f&uuml;r Gas, Wasser &amp; Heizung">',
            '<meta property="og:title" content="Buderus Gasthermenwartung Wien | 24h Notdienst &amp; Service">',
            $html
        );

        $html = str_replace(
            '<meta property="og:description" content="24 Stunden Buderus Gasthermenwartung in Wien &amp; Nieder&ouml;sterreich. Schnelle Thermenreparatur, Wartung &amp; Heizungsservice durch erfahrene Techniker. Jetzt anrufen: +43 1 442 0407.">',
            '<meta property="og:description" content="Buderus Gasthermenwartung in Wien, Nieder&ouml;sterreich und Burgenland: Wartung, Reparatur &amp; Thermentausch vom Meisterbetrieb. 24h Notdienst: +43 1 4420408.">',
            $html
        );

        if (!str_contains($html, 'name="twitter:title"')) {
            $html = str_replace(
                '</head>',
                "<meta name=\"twitter:title\" content=\"Buderus Gasthermenwartung Wien | 24h Notdienst &amp; Service\">\n<meta name=\"twitter:description\" content=\"Buderus Gasthermenwartung in Wien, Nieder&ouml;sterreich und Burgenland: Wartung, Reparatur &amp; Thermentausch vom Meisterbetrieb. 24h Notdienst: +43 1 4420408.\">\n</head>",
                $html
            );
        }

        $html = str_replace(
            '<h1 class="elementor-heading-title elementor-size-default">Buderus-Thermenservice &ndash; Ihr verl&auml;sslicher Partner in Wien und N&Ouml; &amp; Burgenland</h1>',
            '<h1 class="elementor-heading-title elementor-size-default">Buderus Gasthermenwartung in Wien, N&Ouml; &amp; Burgenland</h1>',
            $html
        );

        $schema = <<<'HTML'
<script type="application/ld+json" class="codex-seo-localbusiness-schema">{"@context":"https://schema.org","@graph":[{"@type":"HVACBusiness","@id":"https://buderus-gasthermenwartung.at/#localbusiness","name":"Buderus Gasthermenwartung","url":"https://buderus-gasthermenwartung.at/","telephone":"+43 1 4420408","email":"office@buderus-gasthermenwartung.at","image":"https://buderus-gasthermenwartung.at/assets/images/BUDERUS-Logo-e1753491977281-300x225-1-bd5dc3daf4.webp","priceRange":"ab 89 EUR","address":{"@type":"PostalAddress","streetAddress":"Reinlgasse 26","postalCode":"1140","addressLocality":"Wien","addressCountry":"AT"},"geo":{"@type":"GeoCoordinates","latitude":48.2034402,"longitude":16.3341584},"areaServed":[{"@type":"AdministrativeArea","name":"Wien"},{"@type":"AdministrativeArea","name":"Niederoesterreich"},{"@type":"AdministrativeArea","name":"Burgenland"}],"openingHoursSpecification":[{"@type":"OpeningHoursSpecification","dayOfWeek":["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"],"opens":"00:00","closes":"23:59"}],"sameAs":["https://buderus-gasthermenwartung.at/"]},{"@type":"Service","@id":"https://buderus-gasthermenwartung.at/#service-thermenwartung","serviceType":"Buderus Thermenwartung","provider":{"@id":"https://buderus-gasthermenwartung.at/#localbusiness"},"areaServed":[{"@type":"AdministrativeArea","name":"Wien"},{"@type":"AdministrativeArea","name":"Niederoesterreich"},{"@type":"AdministrativeArea","name":"Burgenland"}],"description":"Buderus Thermenwartung, Service und Notdienst fuer Wien, Niederoesterreich und Burgenland.","offers":{"@type":"Offer","price":"89","priceCurrency":"EUR","availability":"https://schema.org/InStock","url":"https://buderus-gasthermenwartung.at/"}},{"@type":"Service","@id":"https://buderus-gasthermenwartung.at/#service-thermentausch","serviceType":"Buderus Thermentausch","provider":{"@id":"https://buderus-gasthermenwartung.at/#localbusiness"},"areaServed":[{"@type":"AdministrativeArea","name":"Wien"},{"@type":"AdministrativeArea","name":"Niederoesterreich"},{"@type":"AdministrativeArea","name":"Burgenland"}],"description":"Planung und Austausch von Buderus Thermen durch einen Meisterbetrieb.","offers":{"@type":"Offer","priceCurrency":"EUR","availability":"https://schema.org/InStock","url":"https://buderus-gasthermenwartung.at/"}},{"@type":"Service","@id":"https://buderus-gasthermenwartung.at/#service-reparatur","serviceType":"Buderus Thermenreparatur","provider":{"@id":"https://buderus-gasthermenwartung.at/#localbusiness"},"areaServed":[{"@type":"AdministrativeArea","name":"Wien"},{"@type":"AdministrativeArea","name":"Niederoesterreich"},{"@type":"AdministrativeArea","name":"Burgenland"}],"description":"Schnelle Buderus Thermenreparatur und Heizungsnotdienst fuer Wien und Umgebung.","offers":{"@type":"Offer","priceCurrency":"EUR","availability":"https://schema.org/InStock","url":"https://buderus-gasthermenwartung.at/"}}]}</script>
HTML;

        $faqSchema = <<<'HTML'
<script type="application/ld+json" class="codex-seo-faq-schema">{"@context":"https://schema.org","@type":"FAQPage","mainEntity":[{"@type":"Question","name":"Bietet Buderus Gasthermenwartung einen 24h Notdienst an?","acceptedAnswer":{"@type":"Answer","text":"Ja. Auf der Seite wird ein 24h Notdienst fuer Wien, Niederoesterreich und Burgenland beworben."}},{"@type":"Question","name":"In welchen Gebieten ist der Service verfuegbar?","acceptedAnswer":{"@type":"Answer","text":"Der Service wird fuer Wien, Niederoesterreich und Burgenland angeboten."}},{"@type":"Question","name":"Welche Leistungen werden angeboten?","acceptedAnswer":{"@type":"Answer","text":"Auf der Seite werden Buderus Thermenwartung, Thermenreparatur, Thermentausch und Notdienst fuer Gas, Wasser und Heizung genannt."}},{"@type":"Question","name":"Ab welchem Preis startet die Wartungsaktion?","acceptedAnswer":{"@type":"Answer","text":"Die auf der Seite sichtbare Thermenwartungsaktion startet ab 89 Euro."}}]}</script>
HTML;

        if (!str_contains($html, 'codex-seo-localbusiness-schema')) {
            $html = str_replace('</head>', $schema . "\n" . $faqSchema . "\n</head>", $html);
        }

        $noisyWrapper = '<div class="elementor-element elementor-element-0756a2b elementor-widget elementor-widget-text-editor" data-id="0756a2b" data-element_type="widget" data-widget_type="text-editor.default"><div class="elementor-widget-container"><div class="elementor-element elementor-element-fbad4a9 elementor-widget elementor-widget-text-editor" data-id="fbad4a9" data-element_type="widget" data-widget_type="text-editor.default"><div class="elementor-widget-container"><div class="w-full text-token-text-primary" data-testid="conversation-turn-5"><div class="px-4 py-2 justify-center text-base md:gap-6 m-auto"><div class="flex flex-1 text-base mx-auto gap-3 md:px-5 lg:px-1 xl:px-5 md:max-w-3xl lg:max-w-[40rem] xl:max-w-[48rem] group"><div class="relative flex w-full flex-col agent-turn"><div class="flex-col gap-1 md:gap-3"><div class="flex flex-grow flex-col max-w-full"><div class="min-h-[20px] text-message flex flex-col items-start gap-3 whitespace-pre-wrap break-words [.text-message+&amp;]:mt-5 overflow-x-auto" data-message-author-role="assistant" data-message-id="57e60120-31ef-4a62-bb7b-c19909239ee1"><div class="markdown prose w-full break-words dark:prose-invert dark"><p><span style="font-weight: 400;">Wir sorgen f&uuml;r Ihre Sicherheit und Ihren Komfort: Mit professioneller Thermenwartung, zuverl&auml;ssigem Notdienst und modernen Installationen sind wir Ihr starker Partner in Wien, Nieder&ouml;sterreich und Burgenland. Pers&ouml;nlich, transparent und mit echtem Servicebewusstsein.</span></p></div></div></div></div></div></div></div></div></div></div></div></div>';
        $cleanWrapper = '<p>Wir sorgen f&uuml;r Ihre Sicherheit und Ihren Komfort: Mit professioneller Thermenwartung, zuverl&auml;ssigem Notdienst und modernen Installationen sind wir Ihr starker Partner in Wien, Nieder&ouml;sterreich und Burgenland. Pers&ouml;nlich, transparent und mit echtem Servicebewusstsein.</p>';
        $html = str_replace($noisyWrapper, $cleanWrapper, $html);

        $html = str_replace(
            'Buderus Thermenservices."',
            'Buderus Thermenservices.',
            $html
        );

        $html = str_replace(
            'Buderus Kundendienst Wien: 24/7 Hilfe garantiert"',
            'Buderus Kundendienst Wien: 24/7 Hilfe garantiert',
            $html
        );

        $html = str_replace(
            'Installateuren f&uuml;r Thermenservices."',
            'Installateuren f&uuml;r Thermenservices.',
            $html
        );

        $imageReplacements = [
            'src="/assets/images/Gutesiegel-Meister-77237c765d.webp" class="attachment-full size-full wp-image-282" alt=""' => 'src="/assets/images/Gutesiegel-Meister-77237c765d.webp" class="attachment-full size-full wp-image-282" alt="G&uuml;tesiegel Meisterbetrieb f&uuml;r Buderus Thermenservice"',
            'src="/assets/images/BUDERUS-Logo-e1753491977281-300x225-1-bd5dc3daf4.webp" class="attachment-large size-large wp-image-300" alt=""' => 'src="/assets/images/BUDERUS-Logo-e1753491977281-300x225-1-bd5dc3daf4.webp" class="attachment-large size-large wp-image-300" alt="Buderus Logo f&uuml;r Thermenservice Wien"',
            'src="/assets/images/csm_Google-Bewertungen-kaufen_b02868211a-384e66e6c6.webp" class="attachment-large size-large wp-image-289" alt=""' => 'src="/assets/images/csm_Google-Bewertungen-kaufen_b02868211a-384e66e6c6.webp" class="attachment-large size-large wp-image-289" alt="Google Bewertungen f&uuml;r Buderus Thermenservice"',
            'src="/assets/images/Buderus-1d299d6311.webp" class="attachment-large size-large wp-image-416" alt=""' => 'src="/assets/images/Buderus-1d299d6311.webp" class="attachment-large size-large wp-image-416" alt="Buderus Thermentausch in Wien"',
            'src="/assets/images/Buderus-2-8c93d5e280.webp" class="attachment-large size-large wp-image-418" alt=""' => 'src="/assets/images/Buderus-2-8c93d5e280.webp" class="attachment-large size-large wp-image-418" alt="Buderus Thermenreparatur in Wien"',
            'src="https://buderus-gasthermenwartung.at/wp-content/uploads/2023/12/Dein-Abschnittstext-11-e1765398688728.png" title="" alt=""' => 'src="https://buderus-gasthermenwartung.at/wp-content/uploads/2023/12/Dein-Abschnittstext-11-e1765398688728.png" title="" alt="Buderus Thermenservice Kontaktbild"',
            'src="https://buderus-gasthermenwartung.at/wp-content/uploads/2023/12/111-2025_gb172_schnitt_schraeg_v2.png" title="" alt=""' => 'src="https://buderus-gasthermenwartung.at/wp-content/uploads/2023/12/111-2025_gb172_schnitt_schraeg_v2.png" title="" alt="Buderus Gastherme GB172"',
            'src="https://buderus-gasthermenwartung.at/wp-content/uploads/2023/12/buderus_logamax_plus_gb182i_regelung.png" title="" alt=""' => 'src="https://buderus-gasthermenwartung.at/wp-content/uploads/2023/12/buderus_logamax_plus_gb182i_regelung.png" title="" alt="Buderus Regelung f&uuml;r Thermenwartung"',
            'src="https://buderus-gasthermenwartung.at/wp-content/uploads/2023/12/buderus_logamax_plus_gb182i_frontal_schwarz.png" title="" alt=""' => 'src="https://buderus-gasthermenwartung.at/wp-content/uploads/2023/12/buderus_logamax_plus_gb182i_frontal_schwarz.png" title="" alt="Buderus Gastherme frontal"',
            'src="https://buderus-gasthermenwartung.at/wp-content/uploads/2023/12/Bildschirm%C2%ADfoto-2023-12-26-um-16.31.04.png" title="" alt=""' => 'src="https://buderus-gasthermenwartung.at/wp-content/uploads/2023/12/Bildschirm%C2%ADfoto-2023-12-26-um-16.31.04.png" title="" alt="Buderus Fehlercodes und Servicehinweise"',
            'src="https://buderus-gasthermenwartung.at/wp-content/uploads/2023/12/Bildschirm%C2%ADfoto-2023-12-26-um-16.30.13.png" title="" alt=""' => 'src="https://buderus-gasthermenwartung.at/wp-content/uploads/2023/12/Bildschirm%C2%ADfoto-2023-12-26-um-16.30.13.png" title="" alt="Buderus Kundendienst Informationen"',
        ];

        $html = str_replace(array_keys($imageReplacements), array_values($imageReplacements), $html);

        $faqSection = <<<'HTML'
<section class="codex-single-page-faq ast-container" aria-labelledby="codex-faq-heading" style="padding:40px 20px 20px;">
  <div style="max-width:1000px;margin:0 auto;">
    <h2 id="codex-faq-heading">H&auml;ufige Fragen zur Buderus Gasthermenwartung in Wien</h2>
    <div style="margin-top:20px;">
      <h3>Bietet der Buderus Kundendienst einen 24h Notdienst an?</h3>
      <p>Ja. Auf der Seite wird ein 24h Notdienst f&uuml;r Wien, Nieder&ouml;sterreich und Burgenland beworben.</p>
      <h3>In welchen Gebieten ist der Service verf&uuml;gbar?</h3>
      <p>Der Service wird f&uuml;r Wien, Nieder&ouml;sterreich und Burgenland angeboten. Auf der Seite werden au&szlig;erdem zahlreiche Wiener Bezirke und Orte in Nieder&ouml;sterreich genannt.</p>
      <h3>Welche Leistungen werden angeboten?</h3>
      <p>Zu den sichtbaren Leistungen geh&ouml;ren Buderus Thermenwartung, Thermenreparatur, Thermentausch sowie ein Notdienst f&uuml;r Gas, Wasser und Heizung.</p>
      <h3>Ab welchem Preis startet die Wartungsaktion?</h3>
      <p>Die auf der Seite sichtbare Thermenwartungsaktion startet ab 89&nbsp;Euro.</p>
    </div>
  </div>
</section>
HTML;

        if (!str_contains($html, 'codex-single-page-faq')) {
            $html = str_replace('</div><!-- #content -->', $faqSection . "\n</div><!-- #content -->", $html);
        }

        return $html;
    }

    private static function injectDatenschutzEnhancements(string $html): string
    {
        if (!str_contains($html, 'meta name="description"')) {
            $html = str_replace(
                '</title>',
                "</title>\n<meta name=\"description\" content=\"Datenschutzhinweise von Buderus Gasthermenwartung zu Kontaktaufnahme, Datenverarbeitung und Website-Nutzung in Wien, Nieder&ouml;sterreich und Burgenland.\">",
                $html
            );
        }

        return $html;
    }

    private static function injectHelperPageEnhancements(string $html): string
    {
        return $html;
    }

    private static function replaceBrokenImageUrls(string $html): string
    {
        $replacements = [
            '<img src="https://buderus-gasthermenwartung.at/wp-content/uploads/2023/12/Dein-Abschnittstext-11-e1765398688728.png" title="" alt="" loading="lazy">' => '<img src="/assets/images/BUDERUS-Logo-e1753491977281-300x225-1-bd5dc3daf4.webp" title="" alt="Buderus Thermenservice Kontaktbild" loading="lazy">',
            '<img decoding="async" src="https://buderus-gasthermenwartung.at/wp-content/uploads/2023/12/111-2025_gb172_schnitt_schraeg_v2.png" title="" alt="" class="elementor-animation-grow" loading="lazy">' => '<img decoding="async" src="/assets/images/Boiler-Maintenance-5aa71f467e.webp" title="" alt="Buderus Thermenservice Wartung" class="elementor-animation-grow" loading="lazy">',
            '<img decoding="async" src="https://buderus-gasthermenwartung.at/wp-content/uploads/2023/12/buderus_logamax_plus_gb182i_regelung.png" title="" alt="" loading="lazy">' => '<img decoding="async" src="/assets/images/Boiler-Maintenance-5aa71f467e.webp" title="" alt="Buderus Regelung f&uuml;r Thermenwartung" loading="lazy">',
            '<img decoding="async" src="https://buderus-gasthermenwartung.at/wp-content/uploads/2023/12/buderus_logamax_plus_gb182i_frontal_schwarz.png" title="" alt="" loading="lazy">' => '<img decoding="async" src="/assets/images/Buderus-2-8c93d5e280.webp" title="" alt="Buderus Gastherme frontal" loading="lazy">',
            '<img decoding="async" src="https://buderus-gasthermenwartung.at/wp-content/uploads/2023/12/Bildschirm%C2%ADfoto-2023-12-26-um-16.31.04.png" title="" alt="" loading="lazy">' => '<img decoding="async" src="/assets/images/Buderus-1d299d6311.webp" title="" alt="Buderus Fehlercodes und Servicehinweise" loading="lazy">',
            '<img decoding="async" src="https://buderus-gasthermenwartung.at/wp-content/uploads/2023/12/Bildschirm%C2%ADfoto-2023-12-26-um-16.30.13.png" title="" alt="" loading="lazy">' => '<img decoding="async" src="/assets/images/Buderus-2-8c93d5e280.webp" title="" alt="Buderus Kundendienst Informationen" loading="lazy">',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }

    private static function injectFooterLinkStyles(string $html): string
    {
        if (str_contains($html, 'codex-footer-link-fix')) {
            return $html;
        }

        $style = <<<'HTML'
<style class="codex-footer-link-fix">
.elementor-element-6a74058 .elementor-heading-title a,
.elementor-element-8bdb12c .elementor-heading-title a,
.elementor-element-6a74058 .elementor-heading-title a:hover,
.elementor-element-8bdb12c .elementor-heading-title a:hover,
.elementor-element-6a74058 .elementor-heading-title a:focus,
.elementor-element-8bdb12c .elementor-heading-title a:focus {
    color: #fff !important;
}
</style>
HTML;

        return str_replace('</head>', $style . "\n</head>", $html);
    }
}
