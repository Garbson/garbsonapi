<?php
defined('MOODLE_INTERNAL') || die();
require_once("$CFG->libdir/externallib.php");
require_once("$CFG->libdir/completionlib.php"); // Necessário para format_string
require_once($CFG->dirroot . '/mod/quiz/lib.php');

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

    /**
     * Define os parâmetros da função get_all_quizzes.
     */
    public static function get_all_quizzes_parameters() {
        return new external_function_parameters([]); // Sem parâmetros de entrada
    }

    /**
     * Retorna todos os quizzes de todos os cursos visíveis.
     */
    public static function get_all_quizzes() {
        global $DB;

        // Valida o contexto
        self::validate_context(context_system::instance());

        // Busca todos os cursos visíveis
        $courses = $DB->get_records('course', ['visible' => 1], 'fullname ASC', 'id, fullname, shortname');

        $result = [];
        foreach ($courses as $course) {
            $course_context = context_course::instance($course->id);
            
            // Busca todos os módulos do tipo quiz neste curso
            $quizzes = $DB->get_records_sql(
                "SELECT cm.id as cmid, cm.instance, q.*, cs.section as section_number, cs.name as section_name
                 FROM {course_modules} cm
                 JOIN {modules} m ON m.id = cm.module
                 JOIN {quiz} q ON q.id = cm.instance
                 JOIN {course_sections} cs ON cs.id = cm.section
                 WHERE cm.course = :courseid
                 AND m.name = :modulename
                 AND cm.visible = 1
                 ORDER BY cs.section, cm.id",
                ['courseid' => $course->id, 'modulename' => 'quiz']
            );

            if (!empty($quizzes)) {
                $course_quizzes = [];
                
                foreach ($quizzes as $quiz) {
                    // Formata o nome da seção
                    $section_name = format_string($quiz->section_name, true, ['context' => $course_context]);
                    if (trim($section_name) === '') {
                        $section_name = 'Seção ' . $quiz->section_number;
                    }
                    
                    // Busca informações adicionais sobre o quiz
                    $quiz_info = [
                        'id' => (int)$quiz->id,
                        'cmid' => (int)$quiz->cmid,
                        'name' => format_string($quiz->name, true, ['context' => $course_context]),
                        'intro' => format_text($quiz->intro, $quiz->introformat, ['context' => $course_context]),
                        'timeopen' => (int)$quiz->timeopen,
                        'timeclose' => (int)$quiz->timeclose,
                        'timelimit' => (int)$quiz->timelimit,
                        'attempts_allowed' => (int)$quiz->attempts,
                        'grademethod' => (int)$quiz->grademethod,
                        'section_number' => (int)$quiz->section_number,
                        'section_name' => $section_name,
                    ];
                    
                    $course_quizzes[] = $quiz_info;
                }
                
                // Adiciona o curso e seus quizzes ao resultado
                $result[] = [
                    'id' => (int)$course->id,
                    'fullname' => format_string($course->fullname, true, ['context' => $course_context]),
                    'shortname' => format_string($course->shortname, true, ['context' => $course_context]),
                    'quizzes' => $course_quizzes
                ];
            }
        }
        
        return $result;
    }

    /**
     * Define a estrutura de dados retornada pela função get_all_quizzes.
     */
    public static function get_all_quizzes_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'ID do curso'),
                    'fullname' => new external_value(PARAM_TEXT, 'Nome completo do curso'),
                    'shortname' => new external_value(PARAM_TEXT, 'Nome curto do curso'),
                    'quizzes' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'id' => new external_value(PARAM_INT, 'ID do quiz'),
                                'cmid' => new external_value(PARAM_INT, 'ID do módulo do curso'),
                                'name' => new external_value(PARAM_TEXT, 'Nome do quiz'),
                                'intro' => new external_value(PARAM_RAW, 'Introdução do quiz'),
                                'timeopen' => new external_value(PARAM_INT, 'Timestamp de abertura do quiz'),
                                'timeclose' => new external_value(PARAM_INT, 'Timestamp de fechamento do quiz'),
                                'timelimit' => new external_value(PARAM_INT, 'Limite de tempo (em segundos)'),
                                'attempts_allowed' => new external_value(PARAM_INT, 'Número de tentativas permitidas'),
                                'grademethod' => new external_value(PARAM_INT, 'Método de avaliação'),
                                'section_number' => new external_value(PARAM_INT, 'Número da seção'),
                                'section_name' => new external_value(PARAM_TEXT, 'Nome da seção'),
                            )
                        ),
                        'Lista de quizzes do curso'
                    )
                )
            ),
            'Lista de cursos visíveis com seus quizzes'
        );
    }
}