<?php
namespace TYPO3Fluid\Fluid\ViewHelpers;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\Variables\VariableExtractor;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 *
 *
 * <code title="inline notation and custom title">
 * {object -> f:debug(title: 'Custom title')}
 * </code>
 * <output>
 * all properties of {object} nicely highlighted (with custom title)
 * </output>
 *
 * <code title="only output the type">
 * {object -> f:debug(typeOnly: true)}
 * </code>
 * <output>
 * the type or class name of {object}
 * </output>
 *
 * Note: This view helper is only meant to be used during development
 *
 * @api
 */
class DebugViewHelper extends AbstractViewHelper {

	/**
	 * @var boolean
	 */
	protected $escapeChildren = FALSE;

	/**
	 * @var boolean
	 */
	protected $escapeOutput = FALSE;

	/**
	 * @return void
	 */
	public function initializeArguments() {
		parent::initializeArguments();
		$this->registerArgument('typeOnly', 'boolean', 'If TRUE, debugs only the type of variables', FALSE, FALSE);
		$this->registerArgument('levels', 'integer', 'Levels to render when rendering nested objects/arrays', FALSE, 5);
		$this->registerArgument('html', 'boolean', 'Render HTML. If FALSE, output is indented plaintext', FALSE, FALSE);
	}

	/**
	 * Wrapper for \TYPO3Fluid\Flow\var_dump()
	 *
	 * @return string debug string
	 */
	public function render() {
		$typeOnly = $this->arguments['typeOnly'];
		$expressionToExamine = $this->renderChildren();
		if ($typeOnly === TRUE) {
			return (is_object($expressionToExamine) ? get_class($expressionToExamine) : gettype($expressionToExamine));
		}

		$html = $this->arguments['html'];
		$levels = $this->arguments['levels'];
		return static::dumpVariable($expressionToExamine, $html, 1, $levels);
	}

	/**
	 * @param mixed $variable
	 * @param boolean $html
	 * @param integer $level
	 * @param integer $levels
	 * @return string
	 */
	protected static function dumpVariable($variable, $html, $level, $levels) {
		$typeLabel = is_object($variable) ? get_class($variable) : gettype($variable);

		if (!$html) {
			if (is_scalar($variable)) {
				$string = sprintf('%s %s', $typeLabel, var_export($variable, TRUE)) . PHP_EOL;
			} elseif (is_null($variable)) {
				$string = 'null' . PHP_EOL;
			} else {
				$string = sprintf('%s: ', $typeLabel);
				if ($level > $levels) {
					$string .= '*Recursion limited*';
				} else {
					$string .= PHP_EOL;
					foreach (static::getValuesOfNonScalarVariable($variable) as $property => $value) {
						$string .= sprintf(
							'%s"%s": %s',
							str_repeat('  ', $level),
							$property,
							static::dumpVariable($value, $html, $level + 1, $levels)
						);
					}
				}
			}
		} else {
			if (is_scalar($variable) || is_null($variable)) {
				$string = sprintf(
					'<code>%s = %s</code>',
					$typeLabel,
					htmlspecialchars(var_export($variable, TRUE), ENT_COMPAT, 'UTF-8', FALSE)
				);
			} elseif (is_null($variable)) {
				$string = 'null' . PHP_EOL;
			} else {
				$string = sprintf('<code>%s</code>', $typeLabel);
				if ($level > $levels) {
					$string .= '<i>Recursion limited</i>';
				} else {
					$string .= '<ul>';
					foreach (static::getValuesOfNonScalarVariable($variable) as $property => $value) {
						$string .= sprintf(
							'<li>%s: %s</li>',
							$property,
							static::dumpVariable($value, $html, $level + 1, $levels)
						);
					}
					$string .= '</ul>';
				}
			}
		}

		return $string;
	}

	/**
	 * @param mixed $variable
	 * @retrurn array
	 */
	protected static function getValuesOfNonScalarVariable($variable) {
		if ($variable instanceof \ArrayObject || is_array($variable)) {
			return (array) $variable;
		} elseif ($variable instanceof \Iterator) {
			return iterator_to_array($variable);
		} elseif (is_resource($variable)) {
			return stream_get_meta_data($variable);
		} elseif ($variable instanceof \DateTimeInterface) {
			return [
				'class' => get_class($variable),
				'ISO8601' => $variable->format(\DateTime::ISO8601),
				'UNIXTIME' => (integer) $variable->format('U')
			];
		} else {
			$reflection = new \ReflectionObject($variable);
			$properties = $reflection->getProperties();
			$output = array();
			foreach ($properties as $property) {
				$propertyName = $property->getName();
				$output[$propertyName] = VariableExtractor::extract($variable, $propertyName);
			}
			return $output;
		}
	}

}
