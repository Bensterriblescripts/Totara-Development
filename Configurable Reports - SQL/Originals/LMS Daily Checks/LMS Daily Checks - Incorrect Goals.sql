SELECT 

c.shortname AS courseID,c.fullname AS Course,
CONCAT('<a target="_new" href = "%%WWWROOT%%/totara/plan/component.php?id=', CAST(dpp.id AS CHAR), '&c=course&page=0">Learning plan</a>') AS 'Learning plan',
CONCAT('<a target="_new" href = "%%WWWROOT%%/totara/plan/component.php?id=', CAST(dpp.id AS CHAR), '&c=course&d=',CAST(dpca.id AS CHAR), '&title=D\elete">Remove goal</a>') AS 'Remove goal',
prog.fullname,
concat(u.firstname, ' ', u.lastname) AS learner,
u.username AS username,
dpca.id,
(SELECT COUNT(*) FROM prefix_prog_courseset AS pcs JOIN prefix_prog_courseset_course AS pcsc ON pcsc.coursesetid = pcs.id WHERE pcs.programid = prog.id AND pcsc.courseid = c.id) AS ocurrence

FROM prefix_user u
JOIN prefix_dp_plan dpp ON dpp.userid = u.id
JOIN prefix_dp_plan_course_assign dpca ON dpca.planid = dpp.id
JOIN prefix_course c ON dpca.courseid = c.id
JOIN prefix_prog_user_assignment AS progua ON progua.userid = u.id 
JOIN prefix_prog AS prog ON prog.id = progua.programid
JOIN prefix_course_completions p ON p.course = c.id AND p.userid = u.id


WHERE 
(prog.fullname LIKE '%HAE%'
OR prog.fullname LIKE '%Light Automotive Engineering%'
OR prog.fullname LIKE '%Automotive Electrical Engineering%'
OR prog.fullname LIKE '%Automotive Engineering %'
OR prog.fullname LIKE '%direct entry%'
OR prog.fullname LIKE '%Collision Repair Non-structural Repair%'
OR prog.fullname LIKE '%Collision Repair (Non-structural Repair)%'
OR prog.fullname LIKE '%Automotive Refinishing%'
OR prog.fullname LIKE '%Automotive Refinishing (Level 4)%'
OR prog.fullname LIKE '%Automotive Refinishing (Level 4) 2018-01%'
OR prog.fullname LIKE '%Collision Repair (Non-structural Repair) (Level 4) 2018-01%'
OR prog.fullname LIKE '%Collision Repair Non-structural Repair (Level 4)%'
OR prog.fullname LIKE '%Motorcycle Engineering (%')

AND c.shortname NOT LIKE 'FLM-%'

AND prog.fullname NOT LIKE 'COLLISION REPAIR (NON-STRUCTURAL REPAIR) (LEVEL 3-4) 2019-01'
AND prog.fullname NOT LIKE 'Collision Repair (Non-structural Repair) (Level 4) 2018-01'
AND prog.fullname NOT LIKE 'Automotive Refinishing (Level 3-4) 2018-01'
AND prog.fullname NOT LIKE 'Automotive Refinishing (Level 4) 2018-01'
AND prog.fullname NOT LIKE 'Collision Repair and Automotive Refinishing (Level 3) 2018-01'

AND p.timecompleted IS NULL
AND u.username != 'ytbartholomew'
AND u.username != 'steinmjosh@mymitonz.org.nz'
AND u.suspended = 0
AND (SELECT COUNT(*) FROM prefix_prog_courseset AS pcs JOIN prefix_prog_courseset_course AS pcsc ON pcsc.coursesetid = pcs.id WHERE pcs.programid = prog.id AND pcsc.courseid = c.id) = '0'


ORDER BY u.lastname, c.fullname
