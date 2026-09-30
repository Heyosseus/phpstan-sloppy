<?php

declare(strict_types=1);

namespace Heyosseus\PhpstanSloppy;

use Heyosseus\Sloppy\Analysis\RuleRegistry;
use Heyosseus\Sloppy\Configuration\Configuration;
use Heyosseus\Sloppy\Evidence\EvidenceCollector;
use Heyosseus\Sloppy\Sloppy;
use InvalidArgumentException;

/**
 * The `onlyRules` and `excludeRules` parameters.
 *
 * A rule ID nobody ships is refused rather than ignored: `SL17` for `SL107`
 * would otherwise silently report nothing, which reads exactly like clean code.
 */
final readonly class RuleFilter
{
    /**
     * @param  list<string>  $only  Upper case; empty for every rule.
     * @param  list<string>  $exclude  Upper case.
     */
    private function __construct(
        private array $only,
        private array $exclude,
    ) {}

    /**
     * @throws InvalidArgumentException When a parameter names a rule that does not exist.
     */
    public static function for(Options $options, Configuration $configuration): self
    {
        $only = self::normalise($options->onlyRules);
        $exclude = self::normalise($options->excludeRules);

        if ($only !== [] || $exclude !== []) {
            $known = self::knownIds($configuration);

            foreach ([...$only, ...$exclude] as $id) {
                if (! in_array($id, $known, true)) {
                    throw new InvalidArgumentException(self::unknown($id, $known));
                }
            }
        }

        return new self($only, $exclude);
    }

    public function accepts(string $ruleId): bool
    {
        $id = mb_strtoupper($ruleId);

        return ($this->only === [] || in_array($id, $this->only, true))
            && ! in_array($id, $this->exclude, true);
    }

    /**
     * The same Sloppy running only the rules that can be reported, so a rule
     * nobody will see never costs a pass over the project.
     */
    public function narrow(Sloppy $sloppy): Sloppy
    {
        if ($this->only === [] && $this->exclude === []) {
            return $sloppy;
        }

        return $sloppy->onlyRules(array_values(array_filter($sloppy->rules()->ids(), $this->accepts(...))));
    }

    /**
     * Every rule ID this project could report: shipped, custom, diff-only, and
     * the ones switched off or skipped for a missing framework.
     *
     * @return list<string>
     */
    public static function knownIds(Configuration $configuration): array
    {
        $ids = [];

        foreach ([...RuleRegistry::shipped(), ...$configuration->customRules()] as $class) {
            $ids[] = mb_strtoupper((new $class)->id());
        }

        foreach (EvidenceCollector::fromConfiguration($configuration)->ids() as $id) {
            $ids[] = mb_strtoupper($id);
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    private static function normalise(array $ids): array
    {
        $normalised = [];

        foreach ($ids as $id) {
            // `sloppy.SL107` is how the rule appears in PHPStan's output, so it
            // is what people will copy into their configuration.
            $id = mb_strtoupper(trim(preg_replace('/^sloppy\./i', '', trim($id)) ?? ''));

            if ($id !== '') {
                $normalised[] = $id;
            }
        }

        return array_values(array_unique($normalised));
    }

    /**
     * @param  list<string>  $known
     */
    private static function unknown(string $id, array $known): string
    {
        $closest = null;
        $distance = PHP_INT_MAX;

        foreach ($known as $candidate) {
            $candidateDistance = levenshtein($id, $candidate);

            if ($candidateDistance < $distance) {
                [$closest, $distance] = [$candidate, $candidateDistance];
            }
        }

        $message = sprintf('No Sloppy rule has the ID [%s].', $id);

        if ($closest !== null && $distance <= 2) {
            return $message.sprintf(' Did you mean [%s]?', $closest);
        }

        return $message.' Known: '.implode(', ', $known).'.';
    }
}
