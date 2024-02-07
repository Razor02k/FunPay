<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface {
	private mysqli $mysqli;

	public function __construct(mysqli $mysqli) {
		$this->mysqli = $mysqli;
	}

	public function buildQuery(string $query, array $args = []): string {
		try {
			return $this->tryBuildQuery($query, $args);
		} catch (Exception $e) {
			throw new Exception("Ошибка анализа запроса: $query\n{$e->getMessage()}");
		}
	}

	private function tryBuildQuery(string $query, array $args = []): string {
		$blocks = $this->getBlocks($query);
		$k = 0;
		foreach ($blocks as $i => &$block) {
			for (; $k < count($args); $k++) {
				$pos = mb_strpos($block, "?");
				if ($pos === false) {
					break;
				}
				$arg = $args[$k];
				try {
					$specifierPos = $pos + 1;
					$specifier = $block[$specifierPos] ?? " ";
					switch ($specifier) {
						case "d":
							$arg = $this->getInt($arg, $k + 1);
							break;
						case "f":
							$arg = $this->getFloat($arg, $k + 1);
							break;
						case "a":
							$arg = $this->flatten($arg, $k + 1, [$this, "getEscaped"]);
							break;
						case "#":
							$arg = $this->flatten($arg, $k + 1, [$this, "getColumn"]);
							break;
						case " ":
						case ",":
							$arg = $this->getEscaped($arg, $k + 1);
							$specifierPos = $pos;
							break;
						default:
							throw new Exception("Обнаружен не поддерживаемый спецификатор ?$specifier");
					}
				} catch (SkipBlockException) {
					unset($blocks[$i]);
					$arg = "";
				}

				$block = mb_substr($block, 0, $pos) . $arg . mb_substr($block, $specifierPos + 1);
			}
		}

		if ($k < count($args)) {
			throw new Exception("Переданы лишние аргументы для запроса");
		}
		return implode("", $blocks);
	}

	private function getBlocks(string $query): array {
		$blocks = [];
		$blockOpenPos = 0;
		$len = mb_strlen($query);
		for ($i = 0; $i < $len; $i++) {
			switch ($query[$i]) {
				case "{":
					if ($blockOpenPos > 0) {
						throw new Exception("Обнаружен неожиданное начало блока в запросе, позиция $i");
					} elseif (-$blockOpenPos < $i) {
						$blocks[] = mb_substr($query, -$blockOpenPos, $i + $blockOpenPos);
					}
					$blockOpenPos = $i + 1;
					break;
				case "}":
					if ($blockOpenPos < 1) {
						throw new Exception("Обнаружен неожиданный конец блока в запросе, позиция $i");
					}
					$blocks[] = mb_substr($query, $blockOpenPos, $i - $blockOpenPos);
					$blockOpenPos = -$i - 1;
					break;
				default:
			}
		}
		if ($blockOpenPos > 0) {
			throw new Exception("Обнаружен не закрытый блок в запросе");
		} elseif (-$blockOpenPos < $len) {
			$blocks[] = mb_substr($query, -$blockOpenPos);
		}

		return $blocks;
	}

	private function flatten(mixed $arg, int $k, callable $callback): string {
		if (is_array($arg)) {
			$result = [];
			foreach ($arg as $column => $value) {
				if (is_int($column)) {
					$result[] = $callback($value, $k);
				} else {
					$result[] = $this->getColumn($column) . " = " . $callback($value, $k);
				}
			}
			return implode(", ", $result);
		}

		if (is_scalar($arg) || $arg === null) {
			return $callback($arg, $k);
		}

		throw new Exception("Передан недопустимый тип аргумента #$k: " . gettype($arg));
	}

	private function getInt(mixed $arg, int $k): string {
		if ($arg === SpecialValues::Skip) {
			throw new SkipBlockException();
		}
		return match (gettype($arg)) {
			"NULL" => "NULL",
			"integer" => $arg,
			"boolean", "double" => (int)$arg,
			"string" => ((int)($arg) == $arg) ? (int)($arg) : throw new Exception("Значение аргумента #$k невозможно привести к типу int: $arg"),
			default => throw new Exception("Передан недопустимый тип аргумента #$k: " . gettype($arg)),
		};
	}

	private function getFloat(mixed $arg, int $k): string {
		if ($arg === SpecialValues::Skip) {
			throw new SkipBlockException();
		}
		return match (gettype($arg)) {
			"NULL" => "NULL",
			"double" => $arg,
			"boolean", "integer" => (float)$arg,
			"string" => ((float)($arg) == $arg) ? (float)($arg) : throw new Exception("Значение аргумента #$k невозможно привести к типу float: $arg"),
			default => throw new Exception("Передан недопустимый тип аргумента #$k: " . gettype($arg)),
		};
	}

	private function getEscaped(mixed $arg, int $k): string {
		if ($arg === SpecialValues::Skip) {
			throw new SkipBlockException();
		}
		return match (gettype($arg)) {
			"NULL" => "NULL",
			"double", "integer" => $arg,
			"boolean" => (int)$arg,
			"string" => "'" . $this->mysqli->real_escape_string($arg) . "'",
			default => throw new Exception("Передан недопустимый тип аргумента #$k: " . gettype($arg)),
		};
	}

	private function getColumn(string $arg): string {
		return "`$arg`";
	}

	public function skip(): SpecialValues {
		return SpecialValues::Skip;
	}
}
