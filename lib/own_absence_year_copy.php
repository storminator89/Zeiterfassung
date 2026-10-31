<?php
// Shared bounded labels only; no framework/native bootstrap or global constants.
function chrono_own_absence_year_copy(string $language): array
{
    $copy=[
        'de'=>[
            'title'=>'Abwesenheiten', 'load'=>'Jahr anzeigen', 'loaded_at'=>'Geladen (Berliner Zeit)',
            'note'=>'Aktuell gespeicherte Gutschriftprojektionen, keine Urlaubsansprüche, Anträge oder Genehmigungen. Die Berichtsminuten stammen aus den vorhandenen Zeitintervallen, nicht aus einem neu berechneten Sollmodell.',
            'limit'=>'Bis heute gebucht bedeutet nicht verbraucht oder genehmigt. Künftige Buchungen sind keine Freigabezusage. Tage zählen nur verschiedene Daten mit positiver Gutschrift; Nullminuten sind keine Abwesenheitstage. Rest, Übertrag und Verfall werden nicht berechnet.',
            'year'=>'Jahr', 'show'=>'Anzeigen', 'kind'=>'Art', 'phase'=>'Zeitraum', 'records'=>'Buchungen', 'days'=>'Tage mit Gutschrift', 'minutes'=>'Berechnet (Min.)',
            'vacation'=>'Urlaub', 'sickness'=>'Krankheit', 'holiday'=>'Feiertag', 'other'=>'Sonstige',
            'through_today'=>'Bis heute', 'after_today'=>'Künftig', 'unknown'=>'Nicht zuordenbar',
            'current'=>'Auswertbar', 'incomplete'=>'Unvollständig', 'unresolved'=>'Klärung offen',
            'quality'=>'Summen und Tageszahlen sind nicht abschließend: nicht auswertbare Zeitwerte, unbekannte Datumszuordnung oder kollidierende Buchungen benötigen manuelle Klärung. Es wird weder ein Wert ergänzt noch eine Buchung geändert.',
            'unknown_note'=>'Gutschriften ohne sicher zuordenbares Datum erscheinen vorsorglich in jedem gewählten Jahr; sie sind nicht als Buchungen dieses Jahres bestätigt.',
            'details'=>'Gutschriften', 'date'=>'Datum', 'record'=>'Buchung', 'state'=>'Stand', 'review'=>'Korrekturantrag öffnen',
            'empty'=>'Keine Gutschriften für dieses Jahr vorhanden.', 'previous'=>'Vorherige Seite', 'next'=>'Nächste Seite', 'page'=>'Seite', 'of'=>'von',
            'invalid'=>'Jahr oder Seite ungültig. Jahr 1–9999 und eine positive ganze Seitennummer verwenden.', 'unavailable'=>'Jahresübersicht derzeit nicht verfügbar. Bitte neu laden.',
        ],
        'en'=>[
            'title'=>'Own recorded absence credits by year', 'load'=>'Load yearly review', 'loaded_at'=>'Loaded (Berlin time)',
            'note'=>'Current stored credit projections, not leave entitlements, requests or approvals. Report minutes use the existing time intervals, not a newly calculated work model.',
            'limit'=>'Recorded through today does not mean taken or approved. Future records are not approval promises. Days count distinct dates with positive credit; zero minutes are not absence days. Remaining leave, carryover and expiry are not calculated.',
            'year'=>'Year', 'show'=>'Show', 'kind'=>'Kind', 'phase'=>'Record date', 'records'=>'Records', 'days'=>'Positive credit days', 'minutes'=>'Report minutes',
            'vacation'=>'Vacation', 'sickness'=>'Sickness', 'holiday'=>'Public holiday', 'other'=>'Other stored credit',
            'through_today'=>'Through today inclusive', 'after_today'=>'After today', 'unknown'=>'Unassigned',
            'current'=>'Assessable', 'incomplete'=>'Incomplete', 'unresolved'=>'Review pending',
            'quality'=>'Totals and day counts are not final: unassessable timestamps, unknown dates or conflicting records need manual review. No value is filled in and no record is changed.',
            'unknown_note'=>'Credits without a trustworthy date appear conservatively in every selected year; they are not confirmed as records of that year.',
            'details'=>'Individual current credit projections', 'date'=>'Berlin start date', 'record'=>'Record', 'state'=>'State', 'review'=>'Open correction request',
            'empty'=>'No credits assigned to this year or without a trustworthy date.', 'previous'=>'Previous page', 'next'=>'Next page', 'page'=>'Page', 'of'=>'of',
            'invalid'=>'Invalid year or page. Use year 1–9999 and a positive whole page number.', 'unavailable'=>'Yearly review currently unavailable. Please reload.',
        ],
        'zh'=>[
            'title'=>'按年查看本人已记入的缺勤折算', 'load'=>'加载年度明细', 'loaded_at'=>'加载时间（柏林时间）',
            'note'=>'仅显示当前存储的折算记录，不代表休假额度、申请或批准。报表分钟数使用现有时间区间，不重新计算工作模型。',
            'limit'=>'截至今天的记录不代表已休假或已获批准。未来记录不承诺批准。仅正数折算的不同日期计入天数，零分钟不计为缺勤日。不计算剩余额度、结转或失效。',
            'year'=>'年份', 'show'=>'显示', 'kind'=>'类型', 'phase'=>'记录日期', 'records'=>'记录数', 'days'=>'正数折算天数', 'minutes'=>'报表分钟数',
            'vacation'=>'休假', 'sickness'=>'病假', 'holiday'=>'法定节假日', 'other'=>'其他已存储折算',
            'through_today'=>'截至今天（含今天）', 'after_today'=>'今天之后', 'unknown'=>'日期不明',
            'current'=>'可计算', 'incomplete'=>'不完整', 'unresolved'=>'待核实',
            'quality'=>'合计和天数尚未确定：无法计算的时间、日期不明或记录冲突需要人工核实。不会补填数值或修改记录。',
            'unknown_note'=>'无法可靠确定日期的折算会保守地显示在每个所选年份中，不表示已确认属于该年份。',
            'details'=>'当前折算记录明细', 'date'=>'柏林开始日期', 'record'=>'记录', 'state'=>'状态', 'review'=>'打开更正申请',
            'empty'=>'没有属于该年份或日期不明的折算记录。', 'previous'=>'上一页', 'next'=>'下一页', 'page'=>'页', 'of'=>'/',
            'invalid'=>'年份或页码无效。请使用 1–9999 年及正整数页码。', 'unavailable'=>'年度明细暂不可用，请重新加载。',
        ],
    ];
    return $copy[$language]??$copy['de'];
}

function chrono_own_absence_year_link(string $year,int $page,array $context=[]): string
{
    return 'dashboard.php?'.http_build_query([...array_intersect_key($context,array_flip(['calendar_date','holiday_year'])),
        'absence_year'=>$year,'absence_page'=>$page]).'#own-absence-year';
}
