<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Privacy;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AnonymizationRegistry
{
    /** @var array<class-string, list<AnonymizationRule>> */
    private array $rules = [];

    /**
     * @param array<class-string, list<array{property: string, strategy: string, replacement: ?string}>> $rawRules
     */
    public function __construct(
        #[Autowire(param: 'itk_dev_entity.anonymization_rules')]
        array $rawRules,
    ) {
        foreach ($rawRules as $class => $entries) {
            $this->rules[$class] = array_map(
                static fn (array $r) => new AnonymizationRule(
                    $r['property'],
                    Strategy::from($r['strategy']),
                    $r['replacement'],
                ),
                $entries,
            );
        }
    }

    /**
     * @return list<AnonymizationRule>
     */
    public function rulesFor(string $class): array
    {
        return $this->rules[$class] ?? [];
    }

    /**
     * @return list<class-string>
     */
    public function classes(): array
    {
        return array_keys($this->rules);
    }
}
