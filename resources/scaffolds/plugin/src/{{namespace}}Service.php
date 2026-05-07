<?php

namespace App\Plugins\{{namespace}};

/**
 * Business logic for the {{name}} extension.
 */
class {{namespace}}Service
{
    private $db;

    public function __construct(\App\Core\DatabaseInterface $db)
    {
        $this->db = $db;
    }
}
