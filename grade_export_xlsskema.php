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

require_once($CFG->dirroot.'/grade/export/lib.php');

class grade_export_xlsskema extends grade_export {

    public $plugin = 'xlsskema';

    /**
     * Constructor should set up all the private variables ready to be pulled
     * @param object $course
     * @param int $groupid id of selected group, 0 means all
     * @param stdClass $formdata The validated data from the grade export form.
     */
    public function __construct($course, $groupid, $formdata) {
        parent::__construct($course, $groupid, $formdata);

        // Overrides.
        $this->usercustomfields = true;
    }

    /**
     * To be implemented by child classes
     */
    public function print_grades() {
        global $CFG, $DB;

        require_once($CFG->dirroot.'/lib/excellib.class.php');

        $export_tracking = $this->track_exports();

        $strgrades = get_string('grades');

        // If this file was requested from a form, then mark download as complete (before sending headers).
        \core_form\util::form_download_complete();

        // Calculate file name
        $shortname = format_string($this->course->shortname, true, array('context' => context_course::instance($this->course->id)));
        $downloadfilename = clean_filename("$shortname $strgrades.xls");
        // Creating a workbook
        $workbook = new MoodleExcelWorkbook("-");
        // Sending HTTP headers
        $workbook->send($downloadfilename);
        // Adding the worksheet
        $myxls = $workbook->add_worksheet($strgrades);

        // Print names of all the fields
        $profilefields = grade_helper::get_user_profile_fields($this->course->id, $this->usercustomfields);
        foreach ($profilefields as $id => $field) {
            $myxls->write_string(0, $id, $field->fullname);
        }
        $pos = count($profilefields);
        if (!$this->onlyactive) {
            $myxls->write_string(0, $pos++, get_string("suspended"));
        }
        foreach ($this->columns as $grade_item) {
            foreach ($this->displaytype as $gradedisplayname => $gradedisplayconst) {
                $myxls->write_string(0, $pos++, $this->format_column_name($grade_item, false, $gradedisplayname));
            }
            // Add a column_feedback column
            if ($this->export_feedback) {
                $myxls->write_string(0, $pos++, $this->format_column_name($grade_item, true));
            }
        }
        // Last downloaded column header.
        $myxls->write_string(0, $pos++, get_string('timeexported', 'gradeexport_xlsskema'));
        $myxls->write_string(0, $pos++, get_string('courseshort', 'gradeexport_xlsskema'));
        $myxls->write_string(0, $pos++, get_string('groupname', 'gradeexport_xlsskema'));
        $myxls->write_string(0, $pos++, get_string('groupidnum', 'gradeexport_xlsskema'));

        // Print all the lines of data.
        $i = 0;
        $geub = new grade_export_update_buffer();
        $gui = new graded_users_iterator($this->course, $this->columns, $this->groupid);
        $gui->require_active_enrolment($this->onlyactive);
        $gui->allow_user_custom_fields($this->usercustomfields);
        $gui->init();
        while ($userdata = $gui->next_user()) {
            $i++;
            $user = $userdata->user;

            foreach ($profilefields as $id => $field) {
                $fieldvalue = grade_helper::get_user_field_value($user, $field);
                $myxls->write_string($i, $id, $fieldvalue);
            }
            $j = count($profilefields);
            if (!$this->onlyactive) {
                $issuspended = ($user->suspendedenrolment) ? get_string('yes') : '';
                $myxls->write_string($i, $j++, $issuspended);
            }
            foreach ($userdata->grades as $itemid => $grade) {
                if ($export_tracking) {
                    $status = $geub->track($grade);
                }
                foreach ($this->displaytype as $gradedisplayconst) {
                    $gradestr = $this->format_grade($grade, $gradedisplayconst);
                    if (is_numeric($gradestr)) {
                        $myxls->write_number($i, $j++, $gradestr);
                    } else {
                        $myxls->write_string($i, $j++, $gradestr);
                    }
                }
                // writing feedback if requested
                if ($this->export_feedback) {
                    $myxls->write_string($i, $j++, $this->format_feedback($userdata->feedbacks[$itemid], $grade));
                }
            }
            // Time exported.
            $myxls->write_string($i, $j++, time());

            // Resolve groupname
            $myxls->write_string($i, $j++, $this->course->shortname);

            // Search all the groups of the student for this course.
            $groups = $DB->get_records('groups', ['courseid' => $this->course->id]);
            $usedgroups = [];
            foreach ($groups as $gid => $group){
                // If the group is a manual group, ignore it
                if(empty($group->idnumber)){
                    unset($groups[$gid]);
                    continue;
                }
                $members = $DB->get_records('groups_members', ['groupid' => $gid]);
                // If the group has no members, ignore it
                if(!$members){
                    unset($groups[$gid]);
                    continue;
                }
                $group->members = [];
                foreach ($members as $member){
                    $group->members[$member->userid] = $member->userid;
                }
            }
            $usergroups     = [];
            $usergroupsnb   = [];
            foreach ($groups as $group){
                if(array_key_exists($group->id, $usedgroups)){
                    continue;
                }

                if(isset($group->members[$user->id])){
                    $usergroups[]   = $group->name;
                    if (preg_match('/(\\d{4})_/', $group->idnumber, $matches)) {
                        $usergroupsnb[] = $matches[1];
                    }
                }
            }
            $myxls->write_string($i, $j++, implode(',', $usergroups));
            $myxls->write_string($i, $j++, implode(',', $usergroupsnb));
        }
        $gui->close();
        $geub->close();

    /// Close the workbook
        $workbook->close();

        exit;
    }
}
