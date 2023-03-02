SELECT
CONCAT('<strong><p ', IF(ROUND((UNIX_TIMESTAMP() - attempts.timefinish)/86400,0) > 1, 'style="color:\ red">', ''), IF(ROUND((UNIX_TIMESTAMP() - attempts.timefinish)/86400,0) <= 1, 'style="color:\ green">', ''),'<span class="accesshide" >', CAST(attempts.timefinish as CHAR), '</span>', 
CASE
    WHEN (DATE_FORMAT(FROM_UNIXTIME(attempts.timefinish), '%U') > 39) THEN DATE_FORMAT(FROM_UNIXTIME(attempts.timefinish + 46800),'%d %M %Y %l:%i %p')
    WHEN (DATE_FORMAT(FROM_UNIXTIME(attempts.timefinish), '%U') < 14) THEN DATE_FORMAT(FROM_UNIXTIME(attempts.timefinish + 46800),'%d %M %Y %l:%i %p')
    ELSE DATE_FORMAT(FROM_UNIXTIME(attempts.timefinish + 43200),'%d %M %Y %l:%i %p')
END, '</p></strong>') AS 'Submitted',
CONCAT('<a target="_new" href = "%%WWWROOT%%/user/profile.php?id=', CAST(u.id AS CHAR), '">',u.firstname, ' ', u.lastname, '</a><hr><a target="_new" href = "https://mitocrm.mito.org.nz/main.aspx?etc=2&extraqs=formid%3d85b5f7f3-ac5a-4beb-95da-2fb3e6b50f38&id=%7b',u.idnumber,'%7d&pagetype=entityrecord">',u.username,'</a><hr>', u.phone1) As 'Learner',
CONCAT(u.department, '<hr>',u.phone2) As Employer,
CONCAT(prog.fullname,'<hr><a target="_new" href = "%%WWWROOT%%/course/view.php?id=', CAST(c.id AS CHAR), '">',CAST(c.fullname AS CHAR),'</a><hr><a target="_new" href = "%%WWWROOT%%/mod/quiz/view.php?id=', CAST(cm.id AS CHAR), '">',CAST(quiz.name AS CHAR),'</a>') AS 'Programme and course',
CONCAT('<a target="_new" href = "%%WWWROOT%%/mod/quiz/overrides.php?cmid=', CAST(cm.id AS CHAR), '&mode=user">Add override</a>') AS 'Add override',
CONCAT('<a target="_new" href = "%%WWWROOT%%/mod/quiz/review.php?attempt=', CAST(attempts.id AS CHAR), '">',CAST(attempts.attempt AS CHAR),'</a>') AS 'Attempt',
qover.attempts AS 'Overrides',
ROUND(attempts.sumgrades/quiz.sumgrades*100,1) AS Grade,
CONCAT('<a href="mailto:', u.email, '?cc=elearning@mito.org.nz', IF(ita.email IS null, '', CONCAT('%3B', ita.email)), '&Subject=MITO eLearning – ', c.fullname, ': ', quiz.name, '&body=Kia ora ',u.firstname, '%0D%0A%0D%0AYou have now used more than your allowable attempts at this assessment. %0D%0A%0D%0ABefore attempting again, you need to read the feedback from your previous answers, then review the study material and ensure that you know the answers to the questions. %0D%0A%0D%0AIf you need help with this, please call your Training Advisor and ask for guidance to find the information. %0D%0A%0D%0AWhen you are ready, start your next attempt. %0D%0A%0D%0AThe link to your last marked attempt is here: https://elearning.mito.org.nz/mod/quiz/review.php?attempt=', CAST(attempts.id AS CHAR), '%0D%0A%0D%0AI have unlocked a new attempt for you.%0D%0A%0D%0ANgā mihi%0D%0A%0D%0A%0D%0A%0D%0A" >','Compose email', '</a>') AS 'Compose email',
CONCAT('<a target="_new" href = "%%WWWROOT%%/grade/edit/tree/grade.php?courseid=', CAST(c.id AS CHAR), '&id=',CAST(gg.id AS CHAR),'&gpr_type=report&gpr_plugin=grader&gpr_courseid=', CAST(c.id AS CHAR),'">Add grader feedback</a>') AS 'Add grader feedback',
gg.feedback AS 'Grader feedback',
CONCAT(ita.firstname, ' ', ita.lastname) as 'ita'

FROM
(
  SELECT
  id,
  quiz,
  userid,
  timefinish,
  attempt,
  sumgrades,
  timemodified
  FROM prefix_quiz_attempts
  WHERE (UNIX_TIMESTAMP() - 15778458) < timemodified
  AND attempt > 3
) AS attempts
JOIN prefix_quiz AS quiz 
    ON attempts.quiz = quiz.id
JOIN prefix_course_modules AS cm 
    ON cm.instance = quiz.id
JOIN prefix_user AS u 
    ON u.id = attempts.userid
LEFT JOIN prefix_context AS context 
    ON context.instanceid = u.id
JOIN prefix_role_assignments AS ra 
    ON ra.contextid = context.id
JOIN prefix_role AS r 
    ON r.id = ra.roleid
LEFT JOIN prefix_user AS ita 
    ON ita.id = ra.userid 
LEFT JOIN prefix_quiz_overrides AS qover 
    ON qover.quiz = quiz.id 
    AND qover.userid = u.id
JOIN prefix_course AS c 
    ON c.id = cm.course
    AND c.category != 10
JOIN prefix_grade_items AS gi 
    ON gi.iteminstance = cm.instance 
    AND gi.courseid = c.id
JOIN prefix_grade_grades AS gg 
    ON gg.itemid = gi.id 
    AND gg.userid = u.id
    AND gg.timemodified = attempts.timemodified
LEFT JOIN prefix_prog_user_assignment AS progua 
    ON progua.userid = u.id 
LEFT JOIN prefix_prog AS prog 
    ON prog.id = progua.programid
	
WHERE quiz.preferredbehaviour != 'deferredfeedback'
AND gg.feedback LIKE '%ON HOLD%'
AND (gg.finalgrade < 10 OR gg.finalgrade IS NULL)
AND (attempts.attempt >= qover.attempts OR qover.attempts IS NULL)
AND u.suspended = 0
AND u.username != 'elearning.admin'  AND u.username != 'mito2' AND u.username != 'mito_nzcr' AND u.username != 'mito_nzar' AND u.username != 'kburke' AND u.username != 'iclarke' AND u.username != 'evdemo' AND u.username != 'mito_suig'
AND r.name = 'Supervisor'

GROUP BY attempts.id

ORDER BY attempts.timefinish ASC, u.firstname, u.lastname, quiz.name