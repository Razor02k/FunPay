<?php

namespace FpDbTest;

use Exception;

class DatabaseTest {
	private DatabaseInterface $db;

	public function __construct(DatabaseInterface $db) {
		$this->db = $db;
	}

	public function testBuildQuery(): void {
		$results = $correct = [];

		$results[] = $this->db->buildQuery('SELECT name FROM users WHERE user_id = 1');
		$correct[] = 'SELECT name FROM users WHERE user_id = 1';

		$results[] = $this->db->buildQuery(
			'SELECT * FROM users WHERE name = ? AND block = 0',
			['Jack']
		);
		$correct[] = 'SELECT * FROM users WHERE name = \'Jack\' AND block = 0';

		$results[] = $this->db->buildQuery(
			'SELECT ?# FROM users WHERE user_id = ?d AND block = ?d',
			[['name', 'email'], 2, true]
		);
		$correct[] = 'SELECT `name`, `email` FROM users WHERE user_id = 2 AND block = 1';

		$results[] = $this->db->buildQuery(
			'SELECT ?# FROM users WHERE user_id = ?d AND block = ?d',
			['name', 2, true]
		);
		$correct[] = 'SELECT `name` FROM users WHERE user_id = 2 AND block = 1';

		$results[] = $this->db->buildQuery(
			'UPDATE users SET ?a WHERE user_id = -1',
			[['name' => 'Jack', 'email' => null]]
		);
		$correct[] = 'UPDATE users SET `name` = \'Jack\', `email` = NULL WHERE user_id = -1';

		foreach ([null, true, false] as $block) {
			$results[] = $this->db->buildQuery(
				'SELECT name FROM users WHERE ?# IN (?a){ AND block = ?d}',
				['user_id', [1, 2, 3], $block ?? $this->db->skip()]
			);
		}
		$correct[] = 'SELECT name FROM users WHERE `user_id` IN (1, 2, 3)';
		$correct[] = 'SELECT name FROM users WHERE `user_id` IN (1, 2, 3) AND block = 1';
		$correct[] = 'SELECT name FROM users WHERE `user_id` IN (1, 2, 3) AND block = 0';

		$results[] = $this->db->buildQuery(
			'SELECT * FROM users WHERE name IN (?a) AND block = 0',
			[['Jack', 'Bob', 'Alex']]
		);
		$correct[] = 'SELECT * FROM users WHERE name IN (\'Jack\', \'Bob\', \'Alex\') AND block = 0';

		$results[] = $this->db->buildQuery(
			'SELECT * FROM users WHERE age = ?d AND salary = ?f',
			[20, 200.25]
		);
		$correct[] = 'SELECT * FROM users WHERE age = 20 AND salary = 200.25';

		$results[] = $this->db->buildQuery(
			'SELECT * FROM users WHERE age = ?d AND salary = ?d',
			[20, 200.25]
		);
		$correct[] = 'SELECT * FROM users WHERE age = 20 AND salary = 200';

		$failure = false;
		foreach ($results as $k => $result) {
			if ($result !== $correct[$k]) {
				echo "Ошибка теста $k\nОжидание  : $correct[$k]\nРеальность: $result\n";
				$failure = true;
			}
		}
		if ($failure) {
			throw new Exception('Failure.');
		}
	}
}
