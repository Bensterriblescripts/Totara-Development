SELECT
CONCAT(assessor.firstname, ' ', assessor.lastname) 'Assessor',
c.shortname,
CONCAT('<a href = "%%WWWROOT%%/mod/quiz/review.php?attempt=', CAST(attempts.id AS CHAR), '">',CAST(attempts.attempt AS CHAR),'</a>') AS 'Attempt',
CONCAT('<span class="accesshide" >', CAST(attempts.timefinish as CHAR), '</span>', DATE_FORMAT(FROM_UNIXTIME(attempts.timefinish),'%d %M %Y %H:%i:%s')) AS 'Learner submit date',
CONCAT(u.firstname, ' ', u.lastname) As 'Learner',
DATE_FORMAT(FROM_UNIXTIME(l.timecreated),'%d %M %Y %H:%i:%s') AS 'date assessed',
CONCAT(prog.fullname,'<hr><a target="_new" href = "%%WWWROOT%%/course/view.php?id=', CAST(c.id AS CHAR), '">',CAST(c.fullname AS CHAR),'</a><hr><a target="_new" href = "%%WWWROOT%%/mod/quiz/view.php?id=', CAST(cm.id AS CHAR), '">',CAST(quiz.name AS CHAR),'</a>') AS 'Programme and course',
ROUND(attempts.sumgrades/quiz.sumgrades*100,1) AS Grade

FROM 
prefix_quiz_attempts AS attempts
JOIN prefix_quiz AS quiz 
	ON attempts.quiz = quiz.id
JOIN prefix_course_modules AS cm 
	ON cm.instance = quiz.id
JOIN prefix_user AS u 
	ON u.id = attempts.userid
JOIN prefix_course AS c 
	ON c.id = cm.course
JOIN prefix_grade_items AS gi 
	ON gi.iteminstance = cm.instance 
	AND gi.courseid = c.id
JOIN prefix_grade_grades AS gg 
	ON gg.itemid = gi.id 
	AND gg.userid = u.id
JOIN prefix_prog_user_assignment AS progua 
	ON progua.userid = u.id 
JOIN prefix_prog AS prog 
	ON prog.id = progua.programid
JOIN prefix_logstore_standard_log AS l 
	ON gg.id = l.objectid
JOIN prefix_user AS assessor 
	ON assessor.id = l.userid



WHERE 
(assessor.username in 
(
/* Queue 1 */'rlunn@mymitonz.org.nz',
/* Queue 2 */'savageg@mymitonz.org.nz',
/* Queue 3 */'vhowden@mymitonz.org.nz',
/* Queue 4 */'georgehewison@mymitonz.org.nz',
/* Queue 5 */'johnm_george@mymitonz.org.nz',
/* Queue 6 */'520660@mymitonz.org.nz',
/* Queue 7 */'pdoherty@mymitonz.org.nz',
/* Queue 8 */'511225@mymitonz.org.nz',
/* Queue 9 */'wedwards@mymitonz.org.nz',
/* Queue 10 */'503973@mymitonz.org.nz',
/* Queue 11 */'alexjansen@mymitonz.org.nz',
/* Queue 12 */'simon@mymitonz.org.nz',
/* Queue 13 */'fred_jenkins@mymitonz.org.nz',
/* Queue 14 */'dotnwayne@mymitonz.org.nz',
/* Queue 15 */'ghildred@mymitonz.org.nz',
/* Queue 16 */'harryvvliet@mymitonz.org.nz',
/* Queue 17 */'gregcorbett@mymitonz.org.nz',
/* Other */'656985@mymitonz.org.nz'
))
AND l.action = 'graded'
AND l.target = 'user'
AND gi.itemmodule = 'quiz'
AND quiz.name = 'Written assessment'
AND attempts.sumgrades = quiz.sumgrades
AND ROUND((UNIX_TIMESTAMP() - l.timecreated)/86400,0) < 365

%%FILTER_SEARCHTEXT:(Concat(assessor.firstname,' ',assessor.lastname)):~%%

GROUP BY attempts.uniqueid
ORDER BY l.timecreated DESC