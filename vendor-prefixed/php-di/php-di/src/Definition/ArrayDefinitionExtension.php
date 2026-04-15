<?php
/**
 * @license MIT
 *
 * Modified by eightshift-meilisearch on 01-April-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace EightshiftSeoVendor\DI\Definition;

use EightshiftSeoVendor\DI\Definition\Exception\InvalidDefinition;

/**
 * Extends an array definition by adding new elements into it.
 *
 * @since 5.0
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 */
class ArrayDefinitionExtension extends ArrayDefinition implements ExtendsPreviousDefinition
{
    private ?ArrayDefinition $subDefinition = null;

    public function getValues() : array
    {
        if (! $this->subDefinition) {
            return parent::getValues();
        }

        return array_merge($this->subDefinition->getValues(), parent::getValues());
    }

    public function setExtendedDefinition(Definition $definition) : void
    {
        if (! $definition instanceof ArrayDefinition) {
            throw new InvalidDefinition(sprintf(
                'Definition %s tries to add array entries but the previous definition is not an array',
                $this->getName()
            ));
        }

        $this->subDefinition = $definition;
    }
}
