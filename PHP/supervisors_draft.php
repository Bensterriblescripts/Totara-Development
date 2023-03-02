<?php
namespace local_mitowebservices\handlers;
use local_mitowebservices\utilities as utilities;
use local_mitowebservices\mito_client as mito_client;

class supervisors {
    private $users;
    private $hasusers;
    /**
     * @var $client mito_client
     */
    private $client;
    private function set_users() {
        $filter = \local_mitowebservices\utilities::get_filter('supervisorslastsync', 'modified');
        $persons = $this->client->get_persons(['route' => true, 'value' => $filter]);
        $this->hasusers = true;
        if (isset($persons->headers) && isset($persons->headers['total-records'])) {
            $numberofrecords = $persons->headers['total-records'][0];
            mtrace("There are {$numberofrecords} persons to process.");
            $retrieved = count($persons->contents);
            $users = $persons->contents;
            if ($retrieved == 0) {
                $this->hasusers = false;
                $this->users = [];
                return;
            }
            while ($retrieved < $numberofrecords) {
                $persons = $this->client->get_persons(['route' => true, 'value' => $filter.'&offset='.$retrieved]);
                $retrieved += count($persons->contents);
                mtrace("Retrieved {$retrieved} of {$numberofrecords} persons to update.");
                foreach ($persons->contents as $person) {
                    $users[] = $person;
                }
            }
            $this->users = $users;
        }
    }

    private function set_client() {
        if (!$this->client) {
            $this->client = new mito_client();
        }
    }

    public function __construct() {
        $this->set_client();
        $this->set_users();
    }

    private function position_assignment($userid = null, $positionname = null, $organisationname = 'mito') {
        if (empty($userid)) {
            throw new \coding_exception("Invalid call to \\local_mitowebservices\\handlers\\supervisors->position_assignment(), userid can not be empty");
        }

        if (empty($positionname)) {
            throw new \coding_exception("Invalid call to \\local_mitowebservices\\handlers\\supervisors->position_assignment(), positionname can not be empty");
        }

        global $DB;

        // get the organisation id
        $organisation = $DB->get_record('org', array( 'fullname' => $organisationname ));
        if (!$organisation) {
            return false;
        }

        // get the position id
        $position = $DB->get_record('pos', array( 'fullname' => $positionname ));
        if (!$position) {
            mtrace("\\local_mitowebservices\\handlers\\supervisors->position_assignment(), Position {$positionname} does not exist");
            return false;
        }

        // get the user
        $user = $DB->get_record('user', array( 'id' => $userid));
        if (!$user) {
            mtrace("\\local_mitowebservices\\handlers\\supervisors->position_assignment(), User does not exist");
            return false;
        }

        // check if the user already has this position
        if ($DB->record_exists('job_assignment', array( 'userid' => $userid, 'positionid' => $position->id ))) {
            mtrace("\\local_mitowebservices\\handlers\\supervisors->position_assignment(), user with id {$userid} is already assigned position {$positionname}");
            return false;
        }

        $assigned = utilities::position_assignment($user, $organisation->id, $position->id);

        return $assigned;
    }

    public function process() {
        if (!$this->hasusers) {
            mtrace("... no users to get supervisors for");
            return false;
        }

        global $DB, $CFG;
        require_once("{$CFG->dirroot}/local/mitowebservices/locallib.php");

        foreach ($this->users as $user) {
            mtrace("Processing user {$user->id}");
            if (!isset($user->agents) || count($user->agents) == 0) {
                mtrace("... No supervisors for user {$user->id}\n");
                continue;
            }

            // get the user associated to the supervisor
            $mdluser = $DB->get_record('user', array( 'idnumber' => $user->id ));

            // No user found. Continue to the next user.
            if (!$mdluser) {
                mtrace("Couldn't find user in the LMS with idnumber {$user->id}");
                // Todo: Notify someone that this user doesn't exist. It shouldn't really be in our table.
                continue;
            }

            // get the supervisors and assign to user
            $supervisors = $user->agents;

            // The current supervisors for this user.
            $currentsupervisors = array();

            // Get the current supervisors for this user.
            if ($usercontext = $DB->get_record('context', array('instanceid' => $mdluser->id, 'contextlevel' => CONTEXT_USER))) {
                mtrace('... Found user context.');
                // Get the supervisor role.
                if ($supervisorrole = $DB->get_record('role', array('shortname' => 'supervisor'))) {
                    mtrace('... Found supervisor role.');
                    // Now get any role assignemnts to this user.
                    $currentsupervisors = $DB->get_records(
                        'role_assignments',
                        array('contextid' => $usercontext->id, 'roleid' => $supervisorrole->id)
                    );
                    mtrace('... Found '.count($currentsupervisors).' supervisors currently assigned.');
                    // Now make the keys for this to use the userid of the user that is assigned the supervisor role for this user.
                    foreach ($currentsupervisors as $key => $value) {
                        // Make a new element using the userid as the key.
                        $currentsupervisors[$value->userid] = $value;

                        // Now unset the original index that used the id of the role assignment as the key.
                        unset($currentsupervisors[$key]);
                    }
                }
            }

            // If there are no current supervisors and there are no supervisors defined by ITOMIC then continue.
            if (empty($currentsupervisors) && empty($supervisors)) {
                mtrace("No current supervisors assigned in ITOMIC and the LMS for user {$mdluser->firstname} {$mdluser->lastname}");
                continue;
            }

            // Populate a list of supervisors returned from ITOMIC indexed by the LMS user id.
            $itomicsupervisors = array();

            foreach ($supervisors as $supervisor) {
                // Check that the supervisor exists as a user in the LMS.
                if (!$supervisoruser = $DB->get_record('user', array('idnumber' => $supervisor->id))) {
                    mtrace("... Supervisor {$supervisor->id} doesn't exist in the LMS");
                    continue;
                }

                $itomicsupervisors[$supervisoruser->id] = $supervisoruser;

                // Assign the role.
                // if there are any errors, use this message
                $errordetails = "No user record for supervisor/person with id {$supervisor->id}";

                // get the moodle user object for the supervisor
                if (!$mdlsupervisor = $DB->get_record('user', array( 'idnumber' => $supervisor->id))) {
                    mtrace("... ".$errordetails);
                    utilities::log_error($supervisor, "supervisors", "n/a", $errordetails);
                    continue;
                }

                // If the supervisor is already assigned then don't worry about doing this again.
                if (isset($currentsupervisors[$mdlsupervisor->id])) {
                    mtrace("... Supervisor {$mdlsupervisor->id} is already assigned to this user.");
                    continue;
                }

                // if there are any logs for this error, clear them
                utilities::log_error($supervisor, "supervisors", "n/a", $errordetails, "u", true);

                try {
                    $roleassigned = local_mitowebservices_assign_supervisor($mdluser, $mdlsupervisor);
                    $message = "{$mdlsupervisor->firstname} {$mdlsupervisor->lastname} assigned as a supervisor to user {$mdluser->firstname} {$mdluser->lastname}";
                    if ($this->position_assignment($mdlsupervisor->id, 'supervisor')) {
                        mtrace("... Supervisor - User with id {$mdluser->id} assigned position supervisor");
                    }
                    mtrace("... ".$message);
                } catch (Exception $exception) {
                    mtrace("... ".$exception->getMessage());
                    // todo: how does one get alerted on exception
                }
            }

            // Remove any supervisors that are currently assigned in the LMS but are not in ITOMIC.
            foreach ($currentsupervisors as $key => $value) {
                if (!isset($itomicsupervisors[$key])) {
                    // Get the supervisor user details.
                    $mdlsupervisor = $DB->get_record('user', array('id' => $key));

                    // Unassign this user as a supervisor.
                    mtrace("... Unassigning the user {$mdlsupervisor->firstname} {$mdlsupervisor->lastname} as a supervisor to user {$mdluser->firstname} {$mdluser->lastname}");
                    role_unassign($supervisorrole->id, $key, $usercontext->id);
                }
            }
            mtrace('');
        }
        return true;
    }
}