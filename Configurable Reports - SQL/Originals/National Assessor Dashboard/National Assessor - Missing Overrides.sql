SELECT DISTINCT 
CONCAT('<span class="accesshide" >', CAST(attempts.timefinish as CHAR), '</span>', DATE_FORMAT(FROM_UNIXTIME(attempts.timefinish),'%d %M %Y %H:%i:%s')) AS 'Submitted',
CONCAT('<span class="accesshide" >', CAST(gg.timemodified as CHAR), '</span>', DATE_FORMAT(FROM_UNIXTIME(gg.timemodified),'%d %M %Y %H:%i:%s')) AS 'Graded',
concat('<a target="_new" href = "https://elearning.mito.org.nz/user/profile.php?id=', CAST(u.id AS CHAR), '">',u.firstname, ' ', u.lastname, '</a><hr><a target="_new" href = "https://crm.mito.org.nz/MITOCRM/main.aspx?etc=2&extraqs=formid%3d85b5f7f3-ac5a-4beb-95da-2fb3e6b50f38&id=%7b',u.idnumber,'%7d&pagetype=entityrecord">',u.username,'</a><hr>', u.phone1) As 'Learner',
CONCAT(u.department, '<hr>',u.phone2) As Employer,
CONCAT(prog.fullname,'<hr><a target="_new" href = "https://elearning.mito.org.nz/course/view.php?id=', CAST(c.id AS CHAR), '">',CAST(c.fullname AS CHAR),'</a><hr><a target="_new" href = "https://elearning.mito.org.nz/mod/quiz/view.php?id=', CAST(cm.id AS CHAR), '">',CAST(quiz.name AS CHAR),'</a>') AS 'Programme and course',
attempts.attempt AS 'Attempt',
qover.attempts AS Overrides,
ROUND(attempts.sumgrades/quiz.sumgrades*100,1) AS 'Grade',
CONCAT('<a target="_new" href = "%%WWWROOT%%/mod/quiz/review.php?attempt=', CAST(attempts.id AS CHAR), '">View attempt</a>') AS 'View attempt',
CONCAT('<a target="_new" href = "%%WWWROOT%%/mod/quiz/overrides.php?cmid=', CAST(cm.id AS CHAR), '&mode=user">Add override</a>') AS 'Add override'

FROM prefix_quiz_attempts attempts
JOIN prefix_quiz quiz ON attempts.quiz=quiz.id
JOIN prefix_course_modules cm ON cm.instance=quiz.id
JOIN prefix_user u ON u.id = attempts.userid
LEFT JOIN prefix_quiz_overrides qover ON qover.quiz = quiz.id AND qover.userid = u.id
JOIN prefix_course c ON c.id = cm.course
JOIN prefix_course_categories AS cc ON cc.id = c.category
JOIN prefix_grade_items gi ON gi.iteminstance = cm.instance AND gi.courseid = c.id
JOIN prefix_grade_grades gg ON gg.itemid = gi.id AND gg.userid = u.id
LEFT JOIN prefix_prog_user_assignment AS progua ON progua.userid = u.id 
LEFT JOIN prefix_prog AS prog ON prog.id = progua.programid
LEFT JOIN prefix_course_modules_completion cmc ON cmc.userid = u.id AND cmc.coursemoduleid = cm.id
JOIN prefix_prog_courseset pcs ON pcs.programid=prog.id /*joining the coureset tables ensures only courses are shown that are in the programme this queue is setup for. If this is not included then assessements the learner has submitted in courses outside this programme will also be shown*/
JOIN prefix_prog_courseset_course pcsc ON pcsc.coursesetid = pcs.id AND pcsc.courseid = c.id

WHERE 
quiz.preferredbehaviour = 'deferredfeedback'
AND attempts.timefinish != 0 AND attempts.sumgrades IS NOT NULL
AND (cmc.completionstate IS NULL OR (cmc.completionstate!= 1 AND cmc.completionstate != 2))
AND (gg.finalgrade < 10)
AND u.suspended = 0
AND (qover.attempts IS NULL OR qover.attempts <= attempts.attempt) 
AND prog.fullname IS NOT NULL
/*Admin users, Demo learenrs and test users are hidden from this queue*/
AND u.username != 'elearning.admin'  AND u.username != 'mito2' AND u.username != 'mitotester2' AND u.username != 'mito_nzcr' AND u.username != 'mito_nzar' AND u.username != 'mito1' AND u.username != 'mito_nzlv' AND u.username != 'mito_suig'
AND u.username != '773294@mymitonz.org.nz' AND u.username != '773288@mymitonz.org.nz' AND u.username != '773290@mymitonz.org.nz' AND u.username != 'demolearner'
AND u.username != 'gbalasuriya' AND u.username != 'hclark' AND u.username != 'kahmad'
/**/
AND cc.name != 'COF'

ORDER BY gg.timemodified ASC, u.firstname ASC, gg.timemodified DESC, attempts.attempt desc