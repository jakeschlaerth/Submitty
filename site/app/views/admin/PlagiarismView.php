<?php

namespace app\views\admin;

use app\views\AbstractView;
use app\libraries\FileUtils;

class PlagiarismView extends AbstractView {

    public function plagiarismMainPage($semester, $course, $gradeables_with_plagiarism_result, $refresh_page, $nightly_rerun_info) {
        $this->core->getOutput()->addBreadcrumb('Plagiarism Detection');

        $plagiarism_result_info = [];

        $course_path = $this->core->getConfig()->getCoursePath();
        foreach ($gradeables_with_plagiarism_result as $gradeable) {
            $plagiarism_row = [];
            $plagiarism_row['title'] = $gradeable['g_title'];
            $plagiarism_row['id'] = $gradeable['g_id'];
            $plagiarism_row['delete_form_action'] = $this->core->buildCourseUrl([
                'plagiarism',
                'gradeable',
                $plagiarism_row['id'],
                'delete'
            ]);
            if (file_exists($course_path . "/lichen/ranking/" . $plagiarism_row['id'] . ".txt")) {
                $timestamp = date("F d Y H:i:s.", filemtime($course_path . "/lichen/ranking/" . $plagiarism_row['id'] . ".txt"));
                $students = array_diff(scandir($course_path . "/lichen/concatenated/" . $plagiarism_row['id']), ['.', '..']);
                $submissions = 0;
                foreach ($students as $student) {
                    $submissions += count(array_diff(scandir($course_path . "/lichen/concatenated/" . $plagiarism_row['id'] . "/" . $student), ['.', '..']));
                }
                $students = count($students);
            }
            else {
                $timestamp = "N/A";
                $students = "N/A";
                $submissions = "N/A";
            }
            $plagiarism_row['timestamp'] = $timestamp;
            $plagiarism_row['students'] = $students;
            $plagiarism_row['submissions'] = $submissions;

            $plagiarism_row['night_rerun_status'] = $nightly_rerun_info[$plagiarism_row['id']] ? "" : "checked";

            // lichen job in queue for this gradeable but processing not started
            if (file_exists("/var/local/submitty/daemon_job_queue/lichen__" . $semester . "__" . $course . "__" . $plagiarism_row['id'] . ".json")) {
                $plagiarism_row['in_queue'] = true;
                $plagiarism_row['processing'] = false;
            }
            elseif (file_exists("/var/local/submitty/daemon_job_queue/PROCESSING_lichen__" . $semester . "__" . $course . "__" . $plagiarism_row['id'] . ".json")) {
                // lichen job in processing stage for this gradeable but not completed
                $plagiarism_row['in_queue'] = true;
                $plagiarism_row['processing'] = true;
            }
            else {
                // no lichen job
                $ranking_file_path = "/var/local/submitty/courses/" . $semester . "/" . $course . "/lichen/ranking/" . $plagiarism_row['id'] . ".txt";
                if (file_get_contents($ranking_file_path) == "") {
                    $plagiarism_row['matches_and_topmatch'] = "0 students matched, N/A top match";
                }
                else {
                    $content = trim(str_replace(["\r", "\n"], '', file_get_contents($ranking_file_path)));
                    $rankings = array_chunk(preg_split('/ +/', $content), 3);
                    $plagiarism_row['ranking_available'] = true;
                    $plagiarism_row['matches_and_topmatch'] = count($rankings) . " students matched, " . $rankings[0][0] . " top match";;
                    $plagiarism_row['gradeable_link'] = count($rankings) . " students matched, " . $rankings[0][0] . " top match";;
                }
                $plagiarism_row['rerun_plagiarism_link'] = $this->core->buildCourseUrl(['plagiarism', 'gradeable', "{$plagiarism_row['id']}", 'rerun']);
                $plagiarism_row['edit_plagiarism_link'] = $this->core->buildCourseUrl(['plagiarism', 'configuration', 'edit']) . "?gradeable_id={$plagiarism_row['id']}";
                $plagiarism_row['nightly_rerun_link'] = $this->core->buildCourseUrl(["plagiarism", "gradeable", "{$plagiarism_row['id']}", "nightly_rerun"]);
            }
            $plagiarism_result_info[] = $plagiarism_row;
        }

         return $this->core->getOutput()->renderTwigTemplate('plagiarism/Plagiarism.twig', [
            "refresh_page" => $refresh_page,
            "plagiarism_results_info" => $plagiarism_result_info,
            "csrf_token" => $this->core->getCsrfToken(),
            "new_plagiarism_config_link" => $this->core->buildCourseUrl(['plagiarism', 'configuration', 'new']),
            "refreshLichenMainPageLink" => $this->core->buildCourseUrl(['plagiarism', 'check_refresh']),
            "semester" => $semester,
            "course" => $course
        ]);
    }

    public function showPlagiarismResult($semester, $course, $gradeable_id, $gradeable_title, $rankings) {
        $this->core->getOutput()->addBreadcrumb('Plagiarism Detection', $this->core->buildCourseUrl(['plagiarism']));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('codemirror', 'codemirror.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('codemirror', 'codemirror.js'));
        $this->core->getOutput()->addInternalJs('plagiarism.js');

        $return = "";
        $return .= <<<HTML
        <script>
        $( document ).ready(function() {
    		setUpPlagView("${gradeable_id}");
		});
        </script>
<div style="padding:5px 5px 0px 5px;" class="full_height content forum_content forum_show_threads">
HTML;

        $return .= $this->core->getOutput()->renderTwigTemplate("admin/PlagiarismHighlightingKey.twig");

        $return .= <<<HTML
        <span style="line-height: 2">Gradeable: <b>$gradeable_title</b> <a style="float:right;" class="btn btn-primary" title="View Key" onclick="$('#Plagiarism-Highlighting-Key').css('display', 'block');">View Key</a></span>
        <hr style="margin-top: 10px;margin-bottom: 10px;" />
        <form id="users_with_plagiarism">
            User 1 (sorted by %match):
            <select name="user_id_1">
                <option value="">None</option>
HTML;
        foreach ($rankings as $ranking) {
            $return .= <<<HTML
                <option value="{$ranking[1]}">$ranking[3] $ranking[4] &lt;$ranking[1]&gt; ($ranking[0])</option>
HTML;
        }

        $return .= <<<HTML
            </select>
            Version:
            <select name="version_user_1">
                <option value="">None</option>
            </select>
            <span style="float:right;"> User 2:
                <select name="user_id_2">
                    <option value="">None</option>
                </select>
                <a name="toggle" class="btn btn-primary" onclick="toggle();">Toggle</a>
            </span>
        </form><br />
        <div style="position:relative; height:80vh; overflow-y:hidden;" class="row">
        <div style="max-height: 100%; width:100%;" class="sub">
        <div style="float:left;width:48%;height:100%;line-height:1.5em;overflow:auto;padding:5px;border: solid 1px #555;background:white;border-width: 2px;">
        <textarea id="code_box_1" name="code_box_1"></textarea>
        </div>
        <div style="float:right;width:48%;height:100%;line-height:1.5em;overflow:auto;padding:5px;border: solid 1px #555;background:white;border-width: 2px;">
        <textarea id="code_box_2" name="code_box_2"></textarea>
        </div>
        </div>
        </div>

HTML;
        $return .= <<<HTML
</div>
<script>

</script>
HTML;
        return $return;
    }

    public function plagiarismPopUpToShowMatches() {
        return <<<HTML
    <ul id="popup_to_show_matches_id" tabindex="0" class="ui-menu ui-widget ui-widget-content ui-autocomplete ui-front" style="display: none;top:0px;left:0px;width:auto;" >
    </ul>
HTML;
    }

    public function configureGradeableForPlagiarismForm($new_or_edit, $gradeable_ids_titles, $prior_term_gradeables, $saved_config, $title) {
        $this->core->getOutput()->addBreadcrumb('Plagiarism Detection', $this->core->buildCourseUrl(['plagiarism']));
        $this->core->getOutput()->addBreadcrumb('Configure New Gradeable');
        $prior_term_gradeables_json = json_encode($prior_term_gradeables);
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();

        #default values for the form
        $gradeable_id = "";
        $all_version = "checked";
        $active_version = "";
        $all_files = "checked";
        $regex_matching_files = "";
        $regex = "";
        $language = ["python" => "selected", "java" => "", "plaintext" => "", "cpp" => "", "mips" => ""];
        $provided_code = "";
        $no_provided_code = "checked";
        $provided_code_filename = "";
        $threshold = "5";
        $sequence_length = "10";
        $prior_term_gradeables_number = $saved_config['prev_term_gradeables'] ? count($saved_config['prev_term_gradeables']) + 1 : 1;
        $ignore_submission_number = $saved_config['ignore_submissions'] ? count($saved_config['ignore_submissions']) + 1 : 1;
        $ignore = "";
        $no_ignore = "checked";


        #values which are in saved configuration
        if ($new_or_edit == "edit") {
            $gradeable_id = $saved_config['gradeable'];
            $all_version = ($saved_config['version'] == "all_version") ? "checked" : "";
            $active_version = ($saved_config['version'] == "active_version") ? "checked" : "";
            if ($saved_config['file_option'] == "matching_regex") {
                $all_files = "";
                $regex_matching_files = "checked";
                $regex = $saved_config['regex'];
            }
            $language[$saved_config['language']] = "selected";

            if ($saved_config["instructor_provided_code"] == true) {
                $provided_code_filename_array = (array_diff(scandir($saved_config["instructor_provided_code_path"]), [".", ".."]));
                foreach ($provided_code_filename_array as $filename) {
                    $provided_code_filename = $filename;
                }
                $provided_code = "checked";
                $no_provided_code = "";
            }

            $threshold = $saved_config['threshold'];
            $sequence_length = $saved_config['sequence_length'];

            if (count($saved_config['ignore_submissions']) > 0) {
                $ignore = "checked";
                $no_ignore = "";
            }
        }

        $return = "";

        if ($new_or_edit == "new") {
            $return .= <<<HTML
                    <select name="gradeable_id">
HTML;
            foreach ($gradeable_ids_titles as $gradeable_id_title) {
                $title = $gradeable_id_title['g_title'];
                $id = $gradeable_id_title['g_id'];
                $return .= <<<HTML
                            <option value="{$id}">$title</option>
HTML;
            }
            $return .= <<<HTML
                    </select>
HTML;
        }
        elseif ($new_or_edit == "edit") {
            $return .= <<<HTML
                    $title
HTML;
        }

        if ($new_or_edit == "edit" && $saved_config["instructor_provided_code"]) {
            $return .= <<<HTML
                    <br />
                    <font size="-1">Current Provided Code: $provided_code_filename</font>
HTML;
        }
        $this->core->getOutput()->renderTwigTemplate('plagiarism/PlagiarismConfigurationForm.twig', [
            "new_or_edit" => $new_or_edit,
            "prior_term_gradeables" => $prior_term_gradeables,
            "ignore_submissions" => $saved_config['ignore_submissions']
        ]);
    }
}
