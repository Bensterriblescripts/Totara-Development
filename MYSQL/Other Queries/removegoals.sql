SELECT *

FROM mdl_dp_plan_course_assign AS dpca
JOIN mdl_dp_plan dpp ON dpca.planid = dpp.id
JOIN mdl_user AS u ON dpp.userid = u.id
JOIN mdl_course c ON dpca.courseid = c.id
JOIN mdl_prog_user_assignment AS progua ON progua.userid = u.id 
JOIN mdl_prog AS prog ON prog.id = progua.programid
JOIN mdl_course_completions AS p ON p.course = c.id AND p.userid = u.id


WHERE p.id IS NOT NULL 
AND dpp.enddate > 1643508503
AND u.id = 26082


ORDER BY dpp.startdate DESC