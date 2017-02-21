<?php
namespace Neos\ContentRepository\EventSourced\Application\Service;

/*
 * This file is part of the Neos.ContentRepository.EventSourced package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain as ContentRepository;
use Neos\ContentRepository\EventSourced\Application\Service\Exception\InvalidDimensionConfigurationException;
use Neos\ContentRepository\EventSourced\Domain\Model\InterDimension;
use Neos\ContentRepository\EventSourced\Domain\Model\IntraDimension;
use Neos\Flow\Annotations as Flow;

/**
 * The fallback graph application service
 *
 * To be used as a read-only source of fallback information for graph-related projectors
 *
 * Never use this on the read side since its initialization time grows linearly
 * by the amount of possible combinations of content dimension values, including editing sessions
 *
 * @Flow\Scope("singleton")
 * @api
 */
class FallbackGraphService
{
    /**
     * @Flow\Inject
     * @var ContentRepository\Repository\WorkspaceRepository
     */
    protected $workspaceRepository;

    /**
     * @Flow\Inject
     * @var ContentRepository\Service\ContentDimensionPresetSourceInterface
     */
    protected $contentDimensionPresetSource;


    /**
     * @var IntraDimension\IntraDimensionalFallbackGraph
     */
    protected $intraDimensionalFallbackGraph;

    /**
     * @var InterDimension\InterDimensionalFallbackGraph
     */
    protected $interDimensionalFallbackGraph;


    /**
     * @throws InvalidDimensionConfigurationException
     */
    public function initializeObject()
    {
        $prioritizedContentDimensions = $this->populateIntraDimensionalFallbackGraph();
        $this->populateInterDimensionalFallbackGraph($prioritizedContentDimensions);
    }

    /**
     * @return array|IntraDimension\ContentDimension[]
     */
    protected function populateIntraDimensionalFallbackGraph(): array
    {
        $prioritizedContentDimensions = [];
        $this->intraDimensionalFallbackGraph = new IntraDimension\IntraDimensionalFallbackGraph();

        $this->populatePresetDimensions($prioritizedContentDimensions);
        $this->populateWorkspaceDimension($prioritizedContentDimensions);

        return $prioritizedContentDimensions;
    }

    protected function populatePresetDimensions(array & $prioritizedContentDimensions)
    {
        foreach ($this->contentDimensionPresetSource->getAllPresets() as $dimensionName => $dimensionConfiguration) {
            $presetDimension = $this->intraDimensionalFallbackGraph->createDimension($dimensionName);
            foreach ($dimensionConfiguration['presets'] as $valueName => $valueConfiguration) {
                if (!isset($valueConfiguration['values'])) {
                    continue;
                }
                $fallbackConfiguration = array_slice($valueConfiguration['values'], 0, 2);
                if (isset($fallbackConfiguration[1])) {
                    if ($presetDimension->getValue($fallbackConfiguration[1])) {
                        $fallbackValue = $presetDimension->getValue($fallbackConfiguration[1]);
                    } else {
                        throw new InvalidDimensionConfigurationException('Unknown fallback value ' . $fallbackConfiguration[1] . ' was for defined for value ' . $fallbackConfiguration[0], 1487617770);
                    }
                } else {
                    $fallbackValue = null;
                }
                $presetDimension->createValue($fallbackConfiguration[0], $fallbackValue);
            }
            $prioritizedContentDimensions[] = $presetDimension;
        }
    }

    protected function populateWorkspaceDimension(array & $prioritizedContentDimensions)
    {
        $workspaceDimension = $this->intraDimensionalFallbackGraph->createDimension('workspace', IntraDimension\ContentDimension::SOURCE_WORKSPACE_REPOSITORY);
        $groupedWorkspaces = [];
        $rootWorkspace = null;
        foreach ($this->workspaceRepository->findAll() as $workspace) {
            /** @var ContentRepository\Model\Workspace $workspace */
            if ($workspace->getBaseWorkspace()) {
                $groupedWorkspaces[$workspace->getBaseWorkspace()->getName()][] = $workspace;
            } else {
                $rootWorkspace = $workspace;
            }
        }

        $this->populateWorkspaceDimensionWithWorkspace($workspaceDimension, $rootWorkspace, null, $groupedWorkspaces);
        $prioritizedContentDimensions[] = $workspaceDimension;
    }

    protected function populateWorkspaceDimensionWithWorkspace(
        IntraDimension\ContentDimension $workspaceDimension,
        ContentRepository\Model\Workspace $variantWorkspace,
        IntraDimension\ContentDimensionValue $fallbackDimensionValue = null,
        array $groupedWorkspaces
    ) {
        $currentDimensionValue = $workspaceDimension->createValue($variantWorkspace->getName(), $fallbackDimensionValue);
        if (isset($groupedWorkspaces[$variantWorkspace->getName()])) {
            foreach ($groupedWorkspaces[$variantWorkspace->getName()] as $workspace) {
                $this->populateWorkspaceDimensionWithWorkspace($workspaceDimension, $workspace, $currentDimensionValue, $groupedWorkspaces);
            }
        }
    }

    /**
     * @param array|IntraDimension\ContentDimension[] $prioritizedContentDimensions
     */
    protected function populateInterDimensionalFallbackGraph(array $prioritizedContentDimensions)
    {
        $this->interDimensionalFallbackGraph = new InterDimension\InterDimensionalFallbackGraph($prioritizedContentDimensions);

        $dimensionValueCombinations = [[]];
        foreach ($prioritizedContentDimensions as $contentDimension) {
            $nextLevelValueCombinations = [];
            foreach ($dimensionValueCombinations as $previousCombination) {
                foreach ($contentDimension->getValues() as $value) {
                    $newCombination = $previousCombination;
                    $newCombination[$contentDimension->getName()] = $value;
                    if ($contentDimension->getSource() === IntraDimension\ContentDimension::SOURCE_PRESET_SOURCE) {
                        if (!$this->contentDimensionPresetSource->isPresetCombinationAllowedByConstraints(
                            $this->translateDimensionValueCombinationToPresetCombination($newCombination)
                        )
                        ) {
                            continue;
                        }
                    }

                    $nextLevelValueCombinations[] = $newCombination;
                }
            }

            $dimensionValueCombinations = $nextLevelValueCombinations;
        }

        $edgeCount = 0;
        foreach ($dimensionValueCombinations as $dimensionValues) {
            $newContentSubgraph = $this->interDimensionalFallbackGraph->createContentSubgraph($dimensionValues);
            foreach ($this->interDimensionalFallbackGraph->getSubgraphs() as $presentContentSubgraph) {
                if ($presentContentSubgraph === $newContentSubgraph
                    || $this->interDimensionalFallbackGraph->normalizeWeight($newContentSubgraph->getWeight())
                    < $this->interDimensionalFallbackGraph->normalizeWeight($presentContentSubgraph->getWeight())
                ) {
                    continue 2;
                }
                try {
                    $this->interDimensionalFallbackGraph->connectSubgraphs($newContentSubgraph, $presentContentSubgraph);
                    $edgeCount++;
                } catch (IntraDimension\Exception\InvalidFallbackException $e) {
                    continue;
                }
            }
        }
    }

    /**
     * @param array|IntraDimension\ContentDimensionValue[] $dimensionValueCombination
     * @return array
     */
    protected function translateDimensionValueCombinationToPresetCombination(array $dimensionValueCombination)
    {
        $presetCombination = [];
        foreach ($dimensionValueCombination as $dimensionName => $dimensionValue) {
            $presetCombination[$dimensionName] = $dimensionValue->getValue();
        }

        return $presetCombination;
    }


    /**
     * @return IntraDimension\IntraDimensionalFallbackGraph
     * @api
     */
    public function getIntraDimensionalFallbackGraph(): IntraDimension\IntraDimensionalFallbackGraph
    {
        return $this->intraDimensionalFallbackGraph;
    }

    /**
     * @return InterDimension\InterDimensionalFallbackGraph
     * @api
     */
    public function getInterDimensionalFallbackGraph(): InterDimension\InterDimensionalFallbackGraph
    {
        return $this->interDimensionalFallbackGraph;
    }
}
