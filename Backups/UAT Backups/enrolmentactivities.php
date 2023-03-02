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

/**
 * Class enrolmentactivities
 *
 * @package     local_mitowebservices\handlers
 * @copyright   2016, LearningWorks <admin@learningworks.co.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrolmentactivities extends handler {
    /**
     * enrolmentactivities constructor.
     *
     * @param array $enrolmentactivities    An array of webservice response objects containing enrolmentactivity
     *                                      information from ITOMIC.
     */
    public function __construct($enrolmentactivities = array()) {
        // The required library files for this.
        $requiredlibraries = array('completionlib.php' => 'libdir', 'datalib.php' => 'libdir');

        // What fields are required in the ITOMIC webservice response object?
        $requiredfields = array(
            'id', 'status',
            'person.id', 'person.username',
            'course.id', 'course.code',
            'activity.id', 'activity.name', 'activity.type',
            'modified'
        );

        // Construct this object.
        parent::__construct($enrolmentactivities, $requiredlibraries, $requiredfields);
    }

    /**
     * Process all enrolment activity objects returned from the ITOMIC webservice.
     *
     * @return bool
     * @throws \moodle_exception
     */
    public function process($justenablemessaging = false) {
        // The parent process function checks for empty webservice responses.
        parent::process();

        global $DB, $CFG;

        // Get the course field names from requiredfields.
        $courseidfield      = $this->get_field('course.id');
        $coursecodefield    = $this->get_field('course.code');

        // Get the user field names from requiredfields.
        $useridfield        = $this->get_field('person.id');
        $userusernamefield  = $this->get_field('person.username');

        // Get the modified field name from the requiredfields.
        $modifiedfield      = $this->get_field('modified');

        // Get the enrolment activity field names.
        $enrolmentactivityidfield   = $this->get_field('activity.id');
        $enrolmentactivitynamefield = $this->get_field('activity.name');
        $enrolmentactivitytypefield = $this->get_field('activity.type');

        // There are objects to process.
        foreach ($this->webserviceresponseobjects as $enrolmentactivity) {
            // Make a json encoded version of this enrolment activity for error logging purposes.
            $enrolmentactivityasjson = json_encode($enrolmentactivity);

            // Convert the enrolment activity lmscomplete text to lowercase.
            $enrolmentactivity->lmscomplete =
                empty($enrolmentactivity->lmscomplete) ? 'false' : \core_text::strtolower($enrolmentactivity->lmscomplete);

            // Convert the enrolment activity status text to lowercase.
            $enrolmentactivity->status =
                empty($enrolmentactivity->status) ? 'not complete' : \core_text::strtolower($enrolmentactivity->status);

            // Stay DRY with this.
            $errormessage = "There was an error trying to process enrolment activity with id '{$enrolmentactivity->id}'. ";

            // Stay DRY with this subject.
            $subject = 'Enrolment activity error';

            if (!$this->has_required_fields($enrolmentactivity)) {
                // Todo: This needs to be logged somewhere and someone alerted perhaps.
                continue;
            }

            // Get the moodle course for this course completion object.
            if (!$course = $DB->get_record('course', array( 'idnumber' => $enrolmentactivity->$courseidfield))) {
                $course = $DB->get_record('course', array( 'shortname' => $enrolmentactivity->$coursecodefield));
            }

            // If the course doesn't exist then we can't continue.
            if (!$course) {
                // Todo: This should be logged somewhere, but more importantly why does it not exist!
                // Generate a message that will be used to send to the support user.
                $errorreason = "The course with id '{$enrolmentactivity->$courseidfield}' doesn't exist in the LMS.";
                $message = $this->get_string('process', $errormessage.$errorreason.$enrolmentactivityasjson);

                // Send the email.
                $this->email_supportuser($subject, $message);
                continue;
            }

            // Find the user associated to this course completion.
            if (!$user = $DB->get_record('user', array( 'idnumber' => $enrolmentactivity->$useridfield))) {
                $user = $DB->get_record('user', array( 'username' => $enrolmentactivity->$userusernamefield));
            }

            // If the user doesn't exist then we can't continue.
            if (!$user) {
                // Todo: This should be logged somewhere. The user may not exist because they haven't been created due to something.
                // Generate a message that will be used to send to the support user.
                $errorreason = "The user with id '{$enrolmentactivity->$useridfield}' doesn't exist in the LMS.";
                $message = $this->get_string('process', $errormessage.$errorreason.$enrolmentactivityasjson);

                // Send the email.
                $this->email_supportuser($subject, $message);
                continue;
            }

            // Now that we know we have a user we will generate a string to use when outputting messages to help identify who
            // the enrolment activity completion is for.
            $userdetailsforoutput = "Couldn't process enrolment activity RPL completion or uncompletion for the following user: ".
                "{$user->firstname} {$user->lastname} ({$user->idnumber}). Reason:";

            // Now we need to verify the enrolment activity (which is essentially a course module).
            // Resolve the enrolment activity name to a module that moodle knows about.
            $enrolmentactivitytype = explode('_', $enrolmentactivity->$enrolmentactivitytypefield);

            // We hope that we get an array. If not we can't continue.
            if (!is_array($enrolmentactivitytype)) {
                // Todo: The enrolment activity hasn't been configured properly in ITOMIC so it would be good if someone is alerted.
                $message = $this->get_string(
                    'process', "{$userdetailsforoutput} The enrolment activity with id {$enrolmentactivity->id} has an invalid ".
                    "type - {$enrolmentactivity->$enrolmentactivitytypefield}."
                );

                // Tell someone.
                mtrace($message);
                continue;
            }

            // The enrolment activity type should only return 2 elements.
            if (count($enrolmentactivitytype) > 2) {
                // Todo: Why is there more elements? Someone needs to be alerted.
                $message = $this->get_string(
                    'process',
                    "{$userdetailsforoutput} The enrolment activity with id {$enrolmentactivity->id} has an invalid type ".
                    "- {$enrolmentactivity->$enrolmentactivitytypefield}."
                );

                // Tell someone that is watching.
                mtrace($message);
                continue;
            }

            // Just in case the enrolment activity type isn't in the format prefix_module
            if (!isset($enrolmentactivitytype[1])) {
                // Todo: Why is this not set? it should be as it is already an array but if not?
                $message = $this->get_string(
                    'process',
                    "{$userdetailsforoutput} The enrolment activity with id {$enrolmentactivity->id} has an invalid type ".
                    "({$enrolmentactivity->$enrolmentactivitytypefield})."
                );

                continue;
            }

            // Now assign the enrolmentactivitytype by index because we have checked it thoroughly.
            $enrolmentactivitytype = $enrolmentactivitytype[1];

            // Now find the id of the module.
            if (!$module = $DB->get_record('modules', array('name' => $enrolmentactivitytype))) {
                // Todo: This should be logged somewhere.
                $message = $this->get_string(
                    'process',
                    "{$userdetailsforoutput} The module {$enrolmentactivitytype} doesn't exist in course ".
                    "'{$course->fullname}' ({$course->idnumber})."
                );

                // Log the message to the screen.
                mtrace($message);
                continue;
            }

            // Now find the associated module in the table.
            // Todo: I need to check that the fields course and name exist in the target table.
            $activitymoduleparams = array('course' => $course->id, 'name' => $enrolmentactivity->$enrolmentactivitynamefield);

            // Use this try catch block to check that my params are ok for the target table. If it doesn't throw an exception
            try {
                $DB->get_record($module->name, $activitymoduleparams);
            } catch (Exception $exception) {
                // Todo: This is probably where we want to know what happened.

                // Tell someone about it.
                mtrace($exception->getTraceAsString());

                $this->email_supportuser($subject, $exception->getTraceAsString());

                continue;
            }

            // Does the module exist for this course?
            if (!$activitymodule = $DB->get_record($module->name, $activitymoduleparams)) {
                $message = $this->get_string(
                    'process',
                    "{$userdetailsforoutput} The {$enrolmentactivitytype} activity module named ".
                    "{$enrolmentactivity->$enrolmentactivitynamefield} doesn't exist in course ".
                    "'{$course->fullname}' ({$course->idnumber})."
                );

                // Tell someone about it.
                mtrace($message);

                continue;
            }

            // Actual get the course module.
            $coursemodule = $DB->get_record(
                'course_modules', array('module' => $module->id, 'course' => $course->id, 'instance' => $activitymodule->id)
            );

            // Check that the course module exists.
            if (!$coursemodule) {
                // If there isn't a course module for this then generate a message to tell someone.
                $message = $this->get_string(
                    'process',
                    "{$userdetailsforoutput} No course module found for ".
                    "enrolment activity {$enrolmentactivity->$enrolmentactivitynamefield} in course ".
                    "'{$course->fullname}' ({$course->idnumber})."
                );

                // Tell someone about it.
                mtrace($message);

                continue;
            }

            // Get the type of completion.
            $completiontype = $DB->get_record(
                'course_completion_criteria',
                array('course' => $course->id, 'module' => $module->name, 'moduleinstance' => $coursemodule->id)
            );

            // Check that this activity module has a completion type.
            if (!$completiontype) {
                // The activity module doesn't have an associated completion type. Generate a message and tell someone.
                $message = $this->get_string(
                    'process',
                    "{$userdetailsforoutput} The {$module->name} activity module {$activitymodule->name} in course ".
                    "'{$course->fullname}'({$course->idnumber}) doesn't have any completion criteria set."
                );

                // Email someone.
                $this->email_supportuser($subject, $message);

                // Tell someone about it.
                mtrace($message);

                continue;
            }

            // Setup the completion info object.
            $completioninfo = new \completion_info($course);

            // Now we can do actually mark this users course as complete via RPL.
            // Firstly, setup the course completion params.
            $params = array( 'userid' => $user->id, 'course' => $course->id, 'criteriaid' => (int)$completiontype->id);

            // Generate completion data for this user in this course with this criteria.
            $completion = new \completion_criteria_completion($params);

            // If the lmscomplete status is also true then we don't need to progress. False would indicate that it has been
            // unset in ITOMIC and in that case we need to continue to unset this.
            if ($completion->rpl == get_string('rpl:coursemodulemessage', 'local_mitowebservices')
                && \core_text::strtolower($enrolmentactivity->status) == 'complete') {

                // Don't set the completion if it is already set by the integrations. Tell someone about it.
                $message = $this->get_string(
                    'process', "RPL completion already set for {$user->firstname} {$user->lastname} ({$user->idnumber})."
                );
                mtrace($message);

                continue;
            }

            if (!($completion->rpl == get_string('rpl:coursemodulemessage', 'local_mitowebservices'))
                && !($enrolmentactivity->status == 'complete')) {
                // They never were complete.

                $message = $this->get_string(
                    'process',
                    "Learner hasn't completed this activity module {$activitymodule->name} in the course ".
                    "'{$course->fullname}'' ({$course->idnumber})."
                );

                mtrace($message);

                continue;
            }

            // Now after all that error checking we need to check that RPL is enabled for this module.
            if (!completion_module_rpl_enabled($completion->get_criteria()->module)) {
                // Todo: Someone really needs to know this via some other alert. For now we are just going to log it to the screen.
                $message = $this->get_string(
                    'process',
                    "{$userdetailsforoutput} RPL completion is not enabled for the activity module ".
                    "{$activitymodule->name} in the course '{$course->fullname}' ({$course->idnumber})."
                );

                $this->email_supportuser($subject, $message);

                // Tell someone about it.
                mtrace($message);

                continue;
            }

            // Now lets prepare some data for the activity completion via RPL to happen.
            $therplactivitycompletion                   = new \stdClass();
            $therplactivitycompletion->id               = 0;
            $therplactivitycompletion->userid           = $user->id;
            $therplactivitycompletion->viewed           = 0;
            $therplactivitycompletion->coursemoduleid   = $coursemodule->id;
            $therplactivitycompletion->timemodified     = time();
            $therplactivitycompletion->timecompleted    = $therplactivitycompletion->timemodified;
            $therplactivitycompletion->reaggregate      = 0;

            // Get the message for course completions via RPL based on this course completions status.
            if (isset($enrolmentactivity->status)) {
                // Anything other than a complete status is an incomplete. Incomplete's are identified by an empty string.
                // An empty string resets any RPL's.
                if (\core_text::strtolower($enrolmentactivity->status) == 'complete') {
                    $rplmessage = get_string('rpl:coursemodulemessage', 'local_mitowebservices');
                } else {
                    $rplmessage = '';
                }
            }

            // If there is not a status field then we assume that they are also not complete.
            if (!isset($enrolmentactivity->status)) {
                $rplmessage = '';
            }

            // Just in case lets make sure that the RPL message is not set.
            if (!isset($rplmessage)) {
                $rplmessage = '';
            }

            // Now based on $rplmessage we will set the completionstate for our completion data.
            $therplactivitycompletion->completionstate = strlen($rplmessage) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;

            // Get the course module information.
            $cm = get_coursemodule_from_id(null, $coursemodule->id, $course->id, false, MUST_EXIST);

            // Update the completion inforation for this.
            $completioninfo->internal_set_data($cm, $therplactivitycompletion);

            if ($therplactivitycompletion->completionstate == COMPLETION_COMPLETE) {
                // Now mark the completion as complete.
                $completion->rpl = $rplmessage;
                $completion->mark_complete();
            } else {
                // Go to the next completion if there is no id for this.
                if (empty($completion->id)) {
                    // Prevent warnings about a completion object with no id. This essentially means that the user doesn't have any
                    // associated activity completion data.
                    $message = $this->get_string(
                        'process',
                        "{$userdetailsforoutput} No activity completion information for this user against ".
                        "the activity module {$activitymodule->name} in".
                        " course {$course->fullname} ({$course->idnumber})."
                    );

                    // Tell someone about it.
                    mtrace($message);

                    continue;
                }

                // If no RPL, uncomplete the user and let aggregation do its thing.
                $completionproperties = array(
                    'timecompleted' => null, 'reaggregate' => time(),
                    'rpl' => null, 'rplgrade' => null,
                    'status' => COMPLETION_STATUS_INPROGRESS
                );

                // Now update the completion status for this activity module.
                $completion->set_properties($completion, $completionproperties);
                $completion->update();
            }

            // The first components of the event to trigger. Completed based on RPL created or deleted.
            $eventclass = "\\report_completion\\event\\rpl_";

            // Some details for outputting the successfull activity module completion or uncompletion. A little DRY here.
            $coursedetails  = "{$course->fullname} ({$course->idnumber})";
            $userdetails    = "{$user->firstname} {$user->lastname} ({$user->idnumber})";

            // A not empty $rplmessage variable is a completed course.
            if (!empty($rplmessage)) {
                $completion->rpl = $rplmessage;
                $completion->mark_complete();
                $eventclass = "{$eventclass}created";

                // Generate a message for this.
                $message = $this->get_string(
                    'process', "RPL activity completion set as complete for {$userdetails} in course {$coursedetails} ".
                    "for activity {$module->name}."
                );

                // Tell someone.
                mtrace($message);
            } else {
                // An empty $rplmessage will un-complete an existing course completion via RPL.
                $completionparams = array(
                    'timecompleted' => null,
                    'reaggregate'   => time(),
                    'rpl'           => null,
                    'rplgrade'      => null,
                    'status'        => COMPLETION_STATUS_INPROGRESS
                );

                $completion->set_properties($completion, $completionparams);
                $completion->update();
                $eventclass = "{$eventclass}deleted";

                // Generate a message for this. This is a little WET.
                // Todo: DRY your eyes mate.
                $message = $this->get_string(
                    'process',
                    "RPL activity completion set as not complete for {$userdetails} in course {$coursedetails} ".
                    "for activity {$module->name}"
                );

                mtrace($message);
            }

            // Only trigger the event if the rpls are different.
            // Todo: Tidy up the rpl event triggering. This shouldn't happen every time otherwise the logs will be littered.
            if (!($completion->rpl == $rplmessage)) {
                // Finally trigger the appropriate event.
                $eventclass::create_from_rpl($user->id, $course->id, $coursemodule->id, $completiontype->id)->trigger();

            }
        }

        // If we are here then all processing completed.
        return true;
    }
}