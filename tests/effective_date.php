<?php

declare(strict_types=1);

// Run: php tests/effective_date.php

require __DIR__ . '/../vendor/autoload.php';

use App\EffectiveDate;

$cases = [
    // title, uploadedAt, expected
    ['Szczepancowa - kamera2 2019-07-30', '2019-07-31T00:12:43.000Z', '2019-07-30T00:12:43Z'],
    ['winnica 2026 11 18', '2026-11-19T04:05:06Z', '2026-11-18T04:05:06Z'],
    ['mpiastowe 2026 10 13', '2026-10-14T11:01:33Z', '2026-10-13T11:01:33Z'],
    ['kamera1 2026-11-18 extra text', '2026-11-18T09:00:00Z', '2026-11-18T09:00:00Z'],
    ['Wojtuś i Franuś', '2021-05-02T10:11:12.000Z', '2021-05-02T10:11:12Z'],           // no date -> upload date
    ['vi 0007 20170615 132752', '2017-06-16T08:14:41Z', '2017-06-16T08:14:41Z'],        // compact form is NOT a match
    ['kamera 2026-13-45', '2026-11-01T01:02:03Z', '2026-11-01T01:02:03Z'],              // invalid date -> upload date
    ['kamera 2026-02-30', '2026-03-01T01:02:03Z', '2026-03-01T01:02:03Z'],              // not a real day
    ['id 12026-11-18', '2026-11-20T00:00:00Z', '2026-11-20T00:00:00Z'],                 // digits glued to the date
    ['kamera 2026-13-45 and 2026-11-02', '2026-11-05T07:08:09Z', '2026-11-02T07:08:09Z'], // skips invalid, takes first valid
    ['old 1850-01-01', '2020-01-01T00:00:00Z', '2020-01-01T00:00:00Z'],                 // implausible year
    ['kamera 2026-11-18', '2026-11-19T01:30:00+01:00', '2026-11-18T00:30:00Z'],         // offsets normalised to UTC
];

$failed = 0;
foreach ($cases as [$title, $uploaded, $expected]) {
    $got = EffectiveDate::resolve($title, $uploaded);
    $ok = $got === $expected;
    $failed += $ok ? 0 : 1;
    printf("%s  %-45s -> %s%s\n", $ok ? 'PASS' : 'FAIL', $title, $got, $ok ? '' : "  (expected $expected)");
}
echo $failed === 0 ? "\nAll " . count($cases) . " passed\n" : "\n$failed failed\n";
exit($failed === 0 ? 0 : 1);
