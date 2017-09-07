<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


/**
 * Library of interface functions and constants for module digitalization
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 * All the digitalization specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package   mod_digitalization
 * @copyright 2011 Patrick Meyer, Tobias Niedl
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');


require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');


//print_r($CFG);

/**
 * In case we do not have a course id or the stored course id is not valid for
 * the current user, we need the user to select one course he/she is able to update
 * and confirm the sent book meta data from the OPAC
 */
function digitalization_course_list() {

    $course_list = array();

    //get all courses of logged in user
    $courses = enrol_get_my_courses();

    //check for each course if user can it edit. If so, take the current course into
    //the list of courses to which the user can add the new digitalization
    foreach($courses as $c)
    {
        $context = get_context_instance(CONTEXT_COURSE,$c->id);

        if (has_capability('moodle/course:update', $context)) {
           $course_list[] = $c;
        }

    }

    return $course_list;
}



/*
 * User has to login because we need to check to which courses he/she
 * can add some digitalization data (a user should add digitalizations
 * only to his own courses!)
 */
require_login();

/*
 * After login process (or if a user is already logged in) we go on here...
 */


/*
 * If user come from InfoGuide directly (so no course_id is stored in the
 * session) he/she had to select a course in the forumlar which is created
 * at the bottom of this script.
 * The formular data is sent again to this script with the param 'id' for
 * the selected course.
 */
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $_SESSION['dig_course_id'] = optional_param('id', '', PARAM_INT);
}



/*
 * If a course ID is stored in the user's session and the user is owner of this course,
 * store the given parameter in the session and redirect to the course...
 */
if(isset($_SESSION['dig_course_id']) && is_numeric($_SESSION['dig_course_id'])) {

    $course_id = $_SESSION['dig_course_id'];

    //Security check: check if logged in user has right to edit the stored course
    $context = get_context_instance(CONTEXT_COURSE, $course_id);
    if (has_capability('moodle/course:update', $context)) {

        //Save parameter information to the current session
        //(Developer Note: don't use PARAM_ALPHAEXT for any filed because it will remove
        // spaces and umlauts)
        $_SESSION['dig_sign']    = optional_param('sign',    '', PARAM_NOTAGS);
        $_SESSION['dig_title']   = optional_param('title',   '', PARAM_NOTAGS);
        $_SESSION['dig_volume']  = optional_param('volume',  '', PARAM_SEQUENCE);
        $_SESSION['dig_issue']   = optional_param('issue',   '', PARAM_SEQUENCE);
        $_SESSION['dig_date']    = optional_param('date',    '', PARAM_NOTAGS);
        $_SESSION['dig_aufirst'] = optional_param('aufirst', '', PARAM_NOTAGS);
        $_SESSION['dig_aulast']  = optional_param('aulast',  '', PARAM_NOTAGS);
        $_SESSION['dig_atitle']  = optional_param('atitle',  '', PARAM_NOTAGS);
        $_SESSION['dig_issn']    = optional_param('issn',    '', PARAM_NOTAGS);
        $_SESSION['dig_isbn']    = optional_param('isbn',    '', PARAM_NOTAGS);
        $_SESSION['dig_publisher'] = optional_param('item-publisher', '', PARAM_NOTAGS);
        $_SESSION['dig_pagecount'] = optional_param('supplemental-item-description', '', PARAM_NOTAGS);


	//2012-01-17 meyerp: Workaround, %2F to / in signature
	/*$_SESSION['dig_sign'] = str_replace(array('%2F'), // to be replaced
					    array('/'), // used to replace
					    $_SESSION['dig_sign']);*/

	/*
	 * Another solution for the string replacement problem. TODO build in security checks
	 */
	$parNames = array('dig_sign', 'dig_title', 'dig_aufirst', 'dig_aulast', 'dig_atitle');

	foreach($parNames as $param) {
		$_SESSION[$param] = urldecode($_SESSION[$param]);//html_entity_decode(x, ENT_COMPAT, 'ISO8859-1')
	}

        if (!isset($_SESSION['dig_section']) || !is_numeric($_SESSION['dig_section'])) {
            $section = 0;
        } else {
            $section = $_SESSION['dig_section'];
        }

        //Redirect to digitalization form to complete the ordering process
        header("Location: ". $CFG->wwwroot ."/course/modedit.php?add=digitalization&type=&return=0".
               "&course=".  $course_id .
               "&section=". $section );

        //Stop executeing script after redirect
        die();

    }
}





/*
 * If no (valid) ID is stored in the session or the user has not the right to update
 * the stored course, a list of courses which the user is able to update is displayed
 */

//Get list of all courses a user is able to update
$course_list = digitalization_course_list();


//Crazy Moodle...
$url = new moodle_url('/test/bridge.php');
$PAGE->set_url($url);

//CONTEXT_SYSTEM just works.
//Crazy Moodle...
$context = get_context_instance(CONTEXT_SYSTEM);
$PAGE->set_context($context);


//Output starts here
$PAGE->set_title(get_string('bridge_title', 'digitalization'));
$PAGE->set_heading(get_string('bridge_title', 'digitalization'));
echo $OUTPUT->header();

//Head line
echo $OUTPUT->heading(get_string('bridge_title', 'digitalization'));



//Page Content
if (empty($course_list)) {

    //User is not a trainer in any course!
    echo $OUTPUT->box_start();
    echo $OUTPUT->error_text(get_string('bridge_error_course_permission', 'digitalization'));
    echo $OUTPUT->box_end();

} else {

    //Show a form with a list of all courses

    /*Following Code should work (documentend classes) but does not (classes not found (not implemented?)) */
    //$form = new html_form();
    //$contents = html_select::make(...)
    //echo $OUTPUT->form($form, $contents);

    /*Solution: 'hard code' HTML Tags here...*/

    //Open form
    echo html_writer::start_tag('form', array('method' => 'get', 'action' => 'bridge.php'));

    //Information Text
    echo html_writer::tag('p', get_string('bridge_select_text', 'digitalization'));

    //Selection dropdown box for courses
    echo html_writer::start_tag('select', array('name' => 'id', 'size' => '1'));
    foreach($course_list as $c) {
        echo html_writer::tag('option', $c->fullname, array('value' => $c->id));
    }
    echo html_writer::end_tag('select');

    //Submit button
    echo html_writer::tag('span', ' ');
    echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('bridge_submit', 'digitalization')));

    //Hidden fields for all params we already get form InfoGuide
    $sign = optional_param('sign', '', PARAM_NOTAGS);
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sign', 'value' => $sign));

    $title = optional_param('title', '', PARAM_NOTAGS);
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'title', 'value' => $title));

    $volume = optional_param('volume', '', PARAM_SEQUENCE);
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'volume', 'value' => $volume));

    $issue = optional_param('issue', '', PARAM_SEQUENCE);
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'issue', 'value' => $issue));

    $date = optional_param('date', '', PARAM_NOTAGS);
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'date', 'value' => $date));

    $volume = optional_param('sign', '', PARAM_NOTAGS);
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'volume', 'value' => $volume));

    $aufirst = optional_param('aufirst', '', PARAM_NOTAGS);
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'aufirst', 'value' => $aufirst));

    $aulast = optional_param('aulast', '', PARAM_NOTAGS);
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'aulast', 'value' => $aulast));

    $atitle = optional_param('atitle', '', PARAM_NOTAGS);
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'atitle', 'value' => $atitle));

    $issn = optional_param('issn', '', PARAM_NOTAGS);
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'issn', 'value' => $issn));

    $isbn = optional_param('isbn', '', PARAM_NOTAGS);
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'isbn', 'value' => $isbn));


    //Close form
    echo html_writer::end_tag('form');
}

// Finish the page
echo $OUTPUT->footer();





?>
