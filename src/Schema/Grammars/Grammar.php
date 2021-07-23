<?php

namespace Neko\Database\Schema\Grammars;

defined('DS') or exit('No direct script access.');

use Neko\Database\Schema\Magic;
use Neko\Database\Schema\Table;
use Neko\Database\Query\Grammars\Grammar as BaseGrammar;

abstract class Grammar extends BaseGrammar
{
    /**
     * Buat sql untuk pembuatan foreign key.
     *
     * @param Table $table
     * @param Magic $command
     *
     * @return string
     */
    public function foreign(Table $table, Magic $command)
    {
        $name = $command->name;
        $table = $this->wrap($table);
        $on = $this->wrap_table($command->on);
        $foreign = $this->columnize($command->columns);
        $referenced = $this->columnize((array) $command->references);

        $sql = 'ALTER TABLE '.$table.' ADD CONSTRAINT '.$name.' ';
        $sql .= 'FOREIGN KEY ('.$foreign.') REFERENCES '.$on.' ('.$referenced.')';
        $sql .= is_null($command->on_delete) ? '' : ' ON DELETE '.$command->on_delete;
        $sql .= is_null($command->on_update) ? '' : ' ON UPDATE '.$command->on_update;

        return $sql;
    }

    /**
     * Buat sql untuk drop tabel.
     *
     * @param Table $table
     * @param Magic $command
     *
     * @return string
     */
    public function drop(Table $table, Magic $command)
    {
        return 'DROP TABLE '.$this->wrap($table);
    }

    /**
     * Buat sql untuk drop constaint.
     *
     * @param Table $table
     * @param Magic $command
     *
     * @return string
     */
    protected function drop_constraint(Table $table, Magic $command)
    {
        return 'ALTER TABLE '.$this->wrap($table).' DROP CONSTRAINT '.$command->name;
    }

    /**
     * Bungkus value identifier keyword.
     *
     * @param Table|string $value
     *
     * @return string
     */
    public function wrap($value)
    {
        if ($value instanceof Table) {
            return $this->wrap_table($value->name);
        } elseif ($value instanceof Magic) {
            $value = $value->name;
        }

        return parent::wrap($value);
    }

    public function wrap_table($table)
    {
        if ($table instanceof \Neko\Database\Query\Expression) {
            return $this->wrap($table);
        }

        $prefix = '';

        if (isset($this->connection->config['prefix'])) {
            $prefix = $this->connection->config['prefix'];
        }

        return $this->wrap($prefix.$table);
    }

    /**
     * Ambil tipe data yang cocok untuk kolom.
     *
     * @param Magic $column
     *
     * @return string
     */
    protected function type(Magic $column)
    {
        return $this->{'type_'.$column->type}($column);
    }

    /**
     * Format value agar bisa digunakan di klausa DEFAULT.
     *
     * @param mixed $value
     *
     * @return string
     */
    protected function default_value($value)
    {
        return is_bool($value) ? (int) $value : (string) $value;
    }

    /**
     * Buat list nama kolom yang dibungkus dan dipisahkan dengan koma.
     *
     * <code>
     *
     *      // Mereturn '"Budi", "Purnomo"' ketika identifiernya berupa tanda kutip
     *      $columns = $grammar->columnize(['Budi', 'Purnomo']);
     *
     * </code>
     *
     * @param array $columns
     *
     * @return string
     */
    final public function columnize($columns)
    {
        return implode(', ', array_map([$this, 'wrap'], $columns));
    }
}
