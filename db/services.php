<?php
defined('MOODLE_INTERNAL') || die();

$functions = array(
    'local_garbsonapi_get_courses_and_sections' => array(
        'classname'   => 'local_garbsonapi_external',
        'methodname'  => 'get_courses_and_sections',
        'classpath'   => 'local/garbsonapi/externallib.php',
        'description' => 'Retorna os nomes dos cursos e das seções (sem módulos)',
        'type'        => 'read',
        'ajax'        => true,
    ),
);