<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Failures;

final class TextReport
{
    public static function format(FailureList $failures, string $runNote = ''): string
    {
        $count = count($failures->entries);
        if ($count === 0) {
            return '';
        }

        $sections = [sprintf('Failed tests (%d)', $count)];
        if ($runNote !== '') {
            $sections[] = $runNote;
        }
        $sections[] = "Locations\n".implode("\n", $failures->locations());
        $divider = str_repeat('-', 72);
        foreach ($failures->entries as $index => $entry) {
            $sections[] = implode("\n", [
                $divider,
                sprintf('[%d/%d] %s - %s', $index + 1, $count, strtoupper($entry->kind), $entry->name),
                $entry->location(),
            ]);
            foreach ($entry->details() as $label => $value) {
                // Indent each nonempty line so rules inside diagnostics stay inside the case.
                $sections[] = $label."\n".preg_replace('/^./m', '  $0', $value);
            }
        }

        return implode("\n\n", $sections)."\n";
    }
}
