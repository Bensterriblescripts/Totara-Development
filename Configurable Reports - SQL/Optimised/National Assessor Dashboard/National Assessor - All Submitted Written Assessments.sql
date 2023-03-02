SELECT DISTINCT 
CONCAT(u.firstname, ' ', u.lastname) AS Learner,
CONCAT('<a target="_new" href = "%%WWWROOT%%/course/view.php?id=', CAST(c.id AS CHAR), '">',CAST(c.fullname AS CHAR),'</a>') AS 'Course',
quiz.name,
attempts.attempt AS 'Attempt',
attempts.id,
IF(qover.attempts IS NULL, '1', qover.attempts) AS 'Attempts allocated',
CONCAT('<a target="_new" href = "%%WWWROOT%%/mod/quiz/review.php?attempt=', CAST(attempts.id AS CHAR), '">View attempt</a>') AS 'View attempt',
CONCAT('Submitted - ', '<span class="accesshide" >', CAST(attempts.timefinish as CHAR), '</span>', DATE_FORMAT(FROM_UNIXTIME(attempts.timefinish),'%d %M %Y')) AS Submitted,
DATE_FORMAT(FROM_UNIXTIME(attempts.timefinish),'%d/%m/%Y') AS D,
ROUND(attempts.sumgrades/quiz.sumgrades*100,1) AS 'Grade'

FROM prefix_quiz_attempts AS attempts
JOIN prefix_quiz AS quiz 
	ON attempts.quiz = quiz.id
JOIN prefix_course_modules AS cm 
	ON cm.instance = quiz.id
    AND quiz.course = cm.course
JOIN prefix_user AS u 
	ON u.id = attempts.userid
LEFT JOIN prefix_quiz_overrides AS qover 
	ON qover.quiz = quiz.id 
    AND qover.userid = u.id
JOIN prefix_course AS c 
	ON c.id = cm.course
JOIN prefix_course_categories AS cc 
	ON cc.id = c.category
LEFT JOIN prefix_prog_user_assignment AS progua 
	ON progua.userid = u.id 
LEFT JOIN prefix_prog AS prog 
	ON prog.id = progua.programid
LEFT JOIN prefix_course_modules_completion cmc 
	ON cmc.userid = u.id 
    AND cmc.coursemoduleid = cm.id

WHERE 
quiz.preferredbehaviour = 'deferredfeedback'

AND u.suspended = 0
AND prog.fullname IS NOT NULL
AND cc.name != 'COF'

/*Admin users, Demo learners and test users are hidden from this queue*/
AND u.username != 'elearning.admin'  AND u.username != 'mito1' AND u.username != 'mito2' AND u.username != 'mito_nzcr' AND u.username != 'mito_nzar' AND u.username != 'mito1' AND u.username != 'mito_nzlv' AND u.username != 'mito_suig'
AND u.username != '773294@mymitonz.org.nz' AND u.username != '773288@mymitonz.org.nz' AND u.username != '773290@mymitonz.org.nz' AND u.username != 'demolearner'
AND u.username != 'gbalasuriya' AND u.username != 'hclark' AND u.username != 'kahmad' 
/**/

/*%%FILTER_SEARCHTEXT:(Concat(u.firstname, ' ', u.lastname, ' ', u.username)):~%%*/

ORDER BY attempts.timefinish DESC