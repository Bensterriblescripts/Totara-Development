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

namespace local_mitowebservices\handlers;
use totara_dashboard\totara\menu\dashboard;

/**
 * Class goalcourses
 *
 * @package     local_mitowebservices\handlers
 * @copyright   2016, LearningWorks <admin@learningworks.co.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class goalcourses extends handler {
    /**
     * goalcourses constructor.
     *
     * @param array $goalcourses    An array of webservice response objects containing a users goalcourse information from ITOMIC.
     */
    public function __construct($goalcourses = array()) {
        // The required library files for this.
        $requiredlibraries = array(
            'totara/plan/lib.php' => 'dirroot',
            'totara/program/lib.php' => 'dirroot',
        );

        // What fields are required in the ITOMIC webservice response object?
        $requiredfields = array('id', 'person.id', 'person.username', 'course.id', 'course.code', 'modified');

        // Construct this object.
        parent::__construct($goalcourses, $requiredlibraries, $requiredfields);
    }

    /**
     * Process all goalcourse objects returned from the ITOMIC webservice.
     *
     * @return bool
     */
    public function process($justenablemessaging = false) {
        // The parent process function checks for empty webservice responses.
        parent::process();

        global $DB;

        // Get the user field names from requiredfields.
        $useridfield        = $this->get_field('person.id');
        $userusernamefield  = $this->get_field('person.username');

        // Get the course field names from requiredfields.
        $courseidfield      = $this->get_field('course.id');
        $coursecodefield    = $this->get_field('course.code');

        // There are objects to process.
        foreach ($this->webserviceresponseobjects as $goalcourse) {
            // Does this goalcourse object have the fields that are required?
            if (!$this->has_required_fields($goalcourse)) {
                // Todo: This needs to be logged somewhere and someone alerted perhaps.
                continue;
            }

            // Get the user for this goal course assignment.
            if (!$user = $DB->get_record('user', array( 'idnumber' => $goalcourse->$useridfield ))) {
                $user = $DB->get_record('user', array( 'username' => $goalcourse->$userusernamefield ));
            }

            // If a user is not found then halt processing for this goal course assignment.
            if (!$user) {
                mtrace($this->get_string('process', "No user found with the idnumber {$goalcourse->$useridfield}"));
                continue;
            }

            // Find the moodle course that maps to the users goal course. This has to match via the idnumber.
            if (!$course = $DB->get_record('course', array( 'idnumber' => $goalcourse->$courseidfield ))) {
                // If the course is not found then halt processing for this goal course assignment.
                mtrace($this->get_string('process',"No course found with the idnumber {$goalcourse->$courseidfield}"));
                continue;
            }

            // All checks passed. Now we can create or add to a learning plan for this user.

            // If the user does not have any existing learning plans then create one.
            $queryparams = array( 'userid' => $user->id, 'status' => DP_PLAN_STATUS_APPROVED );
            if (!$userlearningplan = $DB->get_record('dp_plan', $queryparams)) {

                // Learning plan to start at midnight relative to today.
                $userlearningplanstartdate  = strtotime('today midnight');

                // Learning plan to end at midnight in a years time.
                $userlearningplanenddate    = strtotime('+365 days', $userlearningplanstartdate);

                $transaction = $DB->start_delegated_transaction();

                // Setup the data for the learning plan.
                $userlearningplandata = new \stdClass();

                $userdetails = "{$user->firstname} {$user->lastname} ({$user->idnumber})";

                $userlearningplandata->templateid   = 1;
                $userlearningplandata->userid       = $user->id;
                $userlearningplandata->name         = "Goal courses for {$userdetails}";
                $userlearningplandata->startdate    = $userlearningplanstartdate;
                $userlearningplandata->enddate      = $userlearningplanenddate;
                $userlearningplandata->status       = 0;

                // Todo: this should be a lang string.
                $userlearningplandata->description  =
                    "Learning plan created automatically for {$userdetails} via ITOMIC integration script";

                // Setup the learning plan in the db.
                $userlearningplandata = $DB->insert_record('dp_plan', $userlearningplandata);

                // Create the actual learning plan.
                $userlearningplan = new \development_plan($userlearningplandata);

                // What is the reason for approving the learning plan.
                // Todo: this should also be a lang string.
                $reasonforplanapproval = 'Created automatically via ITOMIC integration script';

                // Update the plan status and plan history.
                $userlearningplan->set_status(DP_PLAN_STATUS_APPROVED, DP_PLAN_REASON_MANUAL_APPROVE, $reasonforplanapproval);

                // Set the learning plan components.
                $learningplancomponents = $userlearningplan->get_components();

                // Only use the component name from each learning plan component.
                foreach ($learningplancomponents as $componentname => $value) {
                    $component = $userlearningplan->get_component($componentname);
                    if ($component->get_setting('enabled')) {
                        // Add items from this component.
                        $component->plan_create_hook();
                    }
                    // Free memory?
                    unset($component);
                }

                $transaction->allow_commit();
            }

            // Create a learning plan object to handle adding courses.
            $userlearningplan = new \development_plan($userlearningplan->id);

            // Get the course component of the learning plan to add courses to.
            $userlearningplancomponent = $userlearningplan->get_component('course');

            // Get any existing goal courses.
            $assigneditems = $userlearningplancomponent->get_assigned_items();

            $coursestoadd = array();
            foreach ($assigneditems as $assigneditem) {
                $coursestoadd[] = $assigneditem->courseid;
            }

            // Check if the course is actually a goal course.
            if (!$goalcourse->goal) {
                $this->unset_goalcourse($userlearningplan, $course);
                continue;
            }

            // Add the new goal course if it doesn't already exist.
            if (!in_array($course->id, $coursestoadd)) {
                $coursestoadd[] = $course->id;
            }

            // Add the courses to the learning plan.
            $userlearningplancomponent->update_assigned_items($coursestoadd);

            // Process the course addition to the users learning plan.
            foreach ($coursestoadd as $courseid) {
                // If the course in the plan is not the one we are processing from this response then don't continue otherwise the
                // all goal course due dates will be overidden.
                if (!($courseid == $course->id)) {
                    continue;
                }

                // Get the plan item id.
                $planitem = $DB->get_record(
                    'dp_plan_course_assign', array( 'courseid' => $courseid, 'planid' => $userlearningplan->id )
                );

                // Set the due date initially if the plan item due date is set.
                if (!empty($planitem->duedate)) {
                    $courseduedate = strtotime($planitem->duedate);
                }

                // Set the course due date.
                if (empty($planitem->duedate)) {
                    // If both the learning plan due date and goalcourse goaldue are null then we need to set this for 13 weeks.
                    if (empty($goalcourse->goaldue)) {
                        $courseduedate = strtotime('+13 weeks');
                    } else {
                        $courseduedate = strtotime($goalcourse->goaldue);
                    }
                }

                if (!empty($planitem->duedate) && !empty($goalcourse->goaldue)) {
                    // Are the due dates different.
                    $itomicgoalcoursedue = strtotime($goalcourse->goaldue);
                    if (!($planitem->duedate == $itomicgoalcoursedue)) {
                        $courseduedate = $itomicgoalcoursedue;
                    } else {
                        $courseduedate = $planitem->duedate;
                    }
                }

                // Fake some form data so we can update the learning plan courses due dates.
                $_POST['updatesettings']    = 1;
                $_POST['ajax']              = 1;
                $_POST['sesskey']           = sesskey();
                $_POST['page']              = 0;
                $_POST['duedate_course']    = array($planitem->id => date('d/m/Y', $courseduedate));

                $userlearningplancomponent->process_settings_update(true);

                // This needs to be unset so that changes do not clash with other course updates.
                unset($_POST['duedate_course']);

                // Enrol the user if the course is not part of their program enrolments.
                $course = $DB->get_record('course', array( 'id' => $courseid));

                // Enrol the user if the course is not part of their program enrolments.
                // First check if the user has a program enrolment for this course.
                $result = prog_can_enter_course($user, $course);

                if (!$result->enroled) {
                    // Need to get the enrolment instance for the totara_learningplan from mdl_enrol.
                    if (!$instance = $DB->get_record('enrol', ['enrol' => 'totara_learningplan', 'courseid' => $course->id])) {
                        // Todo: Perhaps try to enable to learning plan enrolment method. This should be enabled before though.
                        $learningplanenrolment = enrol_get_plugin('totara_learningplan');

                        $learningplanenrolment->add_instance($course);

                        $instance = $DB->get_record('enrol', ['enrol' => 'totara_learningplan', 'courseid' => $course->id]);
                    }

                    // Use our custom enrolment class to enrol the user via learning_plan.
                    $enrol = new \local_mitowebservices\enrol\mito_goalcourse($instance, $user);

                    // The user was not enrolled in the course via a program enrolment. Enrol using the learning plan method.
                    if ($enrol->goalcourse_enrol()) {
                        // Generate a message to output.
                        $message = $this->get_string(
                            'process', "Enrolled user {$user->idnumber } into {$course->fullname} via goalcourse/learning plan."
                        );
                        
                        // Output the message.
                        mtrace($message);
                    }
                }
            }
            // End of processing the user goal course/learning plan.
        }

        return true;
    }

    /**
     * Unset any goal courses that are set to not goals.
     *
     * @param $learningplan     The development_plan object already constructed with the correct data.
     * @param $course           The moodle course to unassign.
     * @return bool             Returns true if the unassignment happend or false if it didn't happen.
     */
    private function unset_goalcourse($learningplan, $course) {
        // Todo: Error checking on the parameters? Should probably do but at this stage it is just me using it.
        global $DB;

        // Get the learningplan id from the learningplan object.
        $learningplanid = $learningplan->id;

        // Get the course to unassign
        $courseidtounassign = $course->id;

        // Get the learningplan assigned courses.
        $plancourseitems = $learningplan->get_component('course')->get_assigned_items();

        // Used to put the actual learning plan course assignment record in.
        $planitem = false;

        // Now get the assigned item for this course. This method is alot safer than assuming we can rely on a planid and a courseid
        // using a SQL statement.
        foreach ($plancourseitems as $plancourseitem) {
            if ($plancourseitem->courseid == $courseidtounassign) {
                // We found the actual plan item with the proper data. Assign it and then get out.
                $planitem = $plancourseitem;
                break;
            }
        }

        // Check that we have a planitem.
        if (!$planitem) {
            // There wasn't a plan item for that course so nothing was unassigned.
            return false;
        }

        // Now we will get the associated record in the table dp_plan_course_assign using the id field.
        $learningplancourseassignment = $DB->get_record('dp_plan_course_assign', array('id' => $planitem->id));

        // Check that there was an associated record for this learningplan course assignment.
        if (!$learningplancourseassignment) {
            // Something went terribly wrong and resulted in a nothing being unassigned.
            return false;
        }

        // Now that we have thoroughly checked the other things before this process we can now unassign the course.
        // This functionality has been extracted from the function unassign_item() in the course component class in totara plan.
        $DB->delete_records(
            'dp_plan_course_assign', array('id' => $learningplancourseassignment->id, 'planid' => $learningplanid)
        );

        // Remove some related things.
        $DB->delete_records(
            'dp_plan_component_relation', array('component1' => 'course', 'itemid1' => $learningplancourseassignment->id)
        );
        $DB->delete_records(
            'dp_plan_component_relation', array('component2' => 'course', 'itemid2' => $learningplancourseassignment->id)
        );

        // Do some tidying up after unassigning the learningplan.

        // Trigger a learning plan component deleted event so things are logged.
        \totara_plan\event\component_deleted::create_from_component(
            $learningplan, 'course', $learningplancourseassignment->id, $course->fullname
        )->trigger();

        // Check plan completion via the totara plan lib.
        dp_plan_check_plan_complete(array($learningplanid));

        // Remove any linked evidence.
        $DB->delete_records(
            'dp_plan_evidence_relation',
            array('planid' => $learningplanid, 'component' => 'course', 'itemid' => $learningplancourseassignment->id)
        );

        // Tell the caller that we were successfull in unassigning the course.
        return true;
    }
}
