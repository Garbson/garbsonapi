<?php
defined('MOODLE_INTERNAL') || die();
require_once("$CFG->libdir/externallib.php");
require_once("$CFG->libdir/completionlib.php"); // Necessário para format_string

class local_garbsonapi_external extends external_api {

    public static function get_courses_and_sections_parameters() {
        return new external_function_parameters([]); // Sem parâmetros de entrada
    }

    /**
     * Retorna uma lista de cursos visíveis com suas respectivas seções (nomes),
     * excluindo a seção geral (seção 0) e os módulos.
     */
    public static function get_courses_and_sections() {
        global $DB;

        // Valida o contexto (geralmente não necessário para leitura de cursos, mas boa prática)
        self::validate_context(context_system::instance());

        // Busca todos os cursos visíveis, ordenados por nome completo
        $courses = $DB->get_records('course', ['visible' => 1], 'fullname ASC', 'id, fullname');

        $result = [];
        foreach ($courses as $course) {
            // Busca as seções do curso, ordenadas pelo número da seção
            $sections = $DB->get_records('course_sections', ['course' => $course->id], 'section ASC', 'id, name, section');

            $sections_arr = [];
            foreach ($sections as $section) {
                // Pula a seção geral (seção 0)
                if ($section->section == 0) {
                    continue;
                }

                // Formata o nome da seção, usando um padrão se estiver vazio
                // A função format_string remove tags HTML e aplica filtros de texto do Moodle
                $sectionname = format_string($section->name, true, ['context' => context_course::instance($course->id)]);
                if (trim($sectionname) === '') {
                    $sectionname = 'Seção ' . $section->section;
                }

                $sections_arr[] = [
                    'id' => (int)$section->id,
                    'name' => $sectionname,
                    'section_number' => (int)$section->section // Renomeado para clareza
                ];
            }

            // Adiciona o curso e suas seções ao resultado
            $result[] = [
                'id' => (int)$course->id,
                'fullname' => $course->fullname,
                'sections' => $sections_arr
            ];
        }
        return $result; // Retorna o array de cursos e seções
    }

    /**
     * Define a estrutura de dados retornada pela função get_courses_and_sections.
     */
    public static function get_courses_and_sections_returns() {
        // Retorna um array (múltiplas estruturas)
        return new external_multiple_structure(
            // Cada elemento do array é uma estrutura única (um curso)
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'ID do curso'),
                    'fullname' => new external_value(PARAM_TEXT, 'Nome completo do curso'),
                    // 'sections' é um array (múltiplas estruturas) de seções
                    'sections' => new external_multiple_structure(
                        // Cada elemento do array de seções é uma estrutura única (uma seção)
                        new external_single_structure(
                            array(
                                'id' => new external_value(PARAM_INT, 'ID da seção'),
                                'name' => new external_value(PARAM_TEXT, 'Nome da seção formatado'),
                                'section_number' => new external_value(PARAM_INT, 'Número da seção (ordem)')
                            )
                        ),
                        'Lista de seções do curso (excluindo seção 0)'
                    )
                )
            ),
            'Lista de cursos visíveis com suas seções'
        );
    }
}