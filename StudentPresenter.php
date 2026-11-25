<?php
declare(strict_types=1);

/**
 * __call() menangani pemanggilan method
 * yang tidak dideklarasikan atau tidak dapat diakses.
 */
final class StudentPresenter
{
    public function __call(string $method, array $args): string
    {
        if ($method !== 'showData') {
            throw new BadMethodCallException(
                "Method tidak dikenal: $method"
            );
        }

        if (count($args) !== 3) {
            throw new InvalidArgumentException(
                'showData harus menerima tepat 3 argumen: id, name, dan major.'
            );
        }

        foreach ($args as $arg) {
            if (!is_string($arg) || trim($arg) === '') {
                throw new InvalidArgumentException(
                    'Semua argumen showData harus berupa string dan tidak boleh kosong.'
                );
            }
        }

        [$id, $name, $major] = $args;

        return 'NIM: ' . $id .
               ', Nama: ' . $name .
               ', Prodi: ' . $major;
    }
}
