JOIN prefix_prog_user_assignment AS progua
    ON progua.userid = attempt_join.userid
JOIN prefix_prog AS prog ON prog.id = progua.programid
    AND prog.fullname IS NOT NULL
    AND prog.fullname != 'Business (First Line Management) (Level 4)'
JOIN prefix_enrol enrol
    ON enrol.courseid = attempt_join.courseid
JOIN prefix_user_enrolments ue
    ON ue.userid = attempt_join.userid
        AND ue.enrolid = enrol.id
        AND ue.status = 0
JOIN prefix_prog_courseset pcs
    ON pcs.programid=prog.id
JOIN prefix_prog_courseset_course pcsc
    ON pcsc.coursesetid = pcs.id
        AND pcsc.courseid = attempt_join.courseid