SELECT DISTINCT
CONCAT('<strong><p ', IF(ROUND((UNIX_TIMESTAMP() - attempt_join.timefinish)/86400,0) > 1, 'style="color:\ red">', ''), IF(ROUND((UNIX_TIMESTAMP() - attempt_join.timefinish)/86400,0) <= 1, 'style="color:\ green">', ''),'<span class="accesshide" >', CAST(attempt_join.timefinish as CHAR), '</span>', DATE_FORMAT(FROM_UNIXTIME(attempt_join.timefinish),'%d %M %Y %H:%i:%s'), '</p></strong>') AS 'Submitted',
CONCAT('<a target="_new" href = "%%WWWROOT%%/user/profile.php?id=', CAST(attempt_join.userid AS CHAR), '">',attempt_join.firstname, ' ', attempt_join.lastname, '</a><hr><a target="_new" href = "https://mitocrm.mito.org.nz/main.aspx?etc=2&extraqs=formid%3d85b5f7f3-ac5a-4beb-95da-2fb3e6b50f38&id=%7b',attempt_join.idnumber,'%7d&pagetype=entityrecord">',attempt_join.username,'</a><hr>', attempt_join.phone1) As 'Learner',
CONCAT(attempt_join.department, '<hr>',attempt_join.phone2) As Employer,
CONCAT(prog.fullname,'<hr><a target="_new" href = "%%WWWROOT%%/course/view.php?id=', CAST(attempt_join.courseid AS CHAR), '">',CAST(attempt_join.fullname AS CHAR),'</a><hr><a target="_new" href = "%%WWWROOT%%/mod/quiz/view.php?id=', CAST(attempt_join.coursemoduleid AS CHAR), '">',CAST(attempt_join.name AS CHAR),'</a>') AS 'Programme and course',
CASE
	WHEN attempt_join.overrideid is not null THEN
		CONCAT('<a target="_new" href = "%%WWWROOT%%/mod/quiz/overrideedit.php?id=', CAST(attempt_join.overrideid AS CHAR), '&mode=user">Add override</a>') 
	ELSE
		CONCAT('<a target="_new" href = "%%WWWROOT%%/mod/quiz/overrideedit.php?action=adduser&cmid=', CAST(attempt_join.coursemoduleid AS CHAR), '&mode=user">Add override</a>') 
END AS 'Add override (new)',
CONCAT('<a target="_new" href = "%%WWWROOT%%/mod/quiz/review.php?attempt=', CAST(attempt_join.attemptid AS CHAR), '">',CAST(attempt_join.attempt AS CHAR),'</a>') AS 'Attempt',
attempt_join.attempts AS 'Overrides',
ROUND(attempt_join.sumgrades/attempt_join.quizgrades*100,1) AS Grade,
CONCAT('<a href="mailto:', attempt_join.email, '?cc=elearning@mito.org.nz', IF(ita.email IS null, '', CONCAT('%3B', ita.email)), '&Subject=MITO eLearning – ', attempt_join.fullname, ': ', attempt_join.name, ' (',attempt_join.username,')', '&body=Kia ora ',attempt_join.firstname, '%0D%0A%0D%0AYou have now used more than your allowable attempts at this assessment. %0D%0A%0D%0ABefore attempting again, you need to read the feedback from your previous answers, then review the study material and ensure that you know the answers to the questions. %0D%0A%0D%0AIf you need help with this, please call your Training Advisor and ask for guidance to find the information. %0D%0A%0D%0AWhen you are ready, start your next attempt. %0D%0A%0D%0AThe link to your last marked attempt is here: https://elearning.mito.org.nz/mod/quiz/review.php?attempt=', CAST(attempt_join.attemptid AS CHAR), ' (Please log in to your mymito learner portal before accessing this link)%0D%0A%0D%0AI have unlocked a new attempt for you.%0D%0A%0D%0ANgā mihi%0D%0A%0D%0A%0D%0A%0D%0A" >','Compose email', '</a>') AS 'Compose email',
CONCAT('<a target="_new" href = "%%WWWROOT%%/grade/edit/tree/grade.php?courseid=', CAST(attempt_join.courseid AS CHAR), '&id=',CAST(gg.id AS CHAR),'&gpr_type=report&gpr_plugin=grader&gpr_courseid=', CAST(attempt_join.courseid AS CHAR),'">Add grader feedback</a>') AS 'Add grader feedback',
gg.feedback AS 'Grader feedback',
CONCAT(ita.firstname, ' ', ita.lastname) as 'ita'

FROM (
SELECT
sql_embed.*,
qover.attempts,
qover.id as 'overrideid'
FROM (

SELECT DISTINCT
LEFT(cc.path, 10) as 'path',
attempts.id as attemptid,
u.firstname,
u.lastname,
u.username,
u.id as idnumber,
u.email,
u.phone1,
u.department,
u.phone2,
c.id as courseid,
c.fullname,
quiz.name,
cm.module,
cm.id as coursemoduleid,
attempts.attempt,
cc.name as coursecategoryname,
cc.path as coursecategorypath,
attempts.timestart,
attempts.timefinish,
attempts.sumgrades,
 
quiz.sumgrades as quizgrades,
quiz.id as quizid,
u.id as userid,
cm.instance as instance

FROM prefix_quiz_attempts attempts
JOIN prefix_quiz quiz 
	ON attempts.quiz=quiz.id 
  	AND quiz.preferredbehaviour != 'deferredfeedback'
JOIN prefix_course_modules cm 
	ON cm.instance=quiz.id
JOIN prefix_user AS u 
	ON u.id = attempts.userid
JOIN prefix_course c 
	ON c.id = cm.course 
JOIN prefix_course_categories AS cc 
	ON cc.id = c.category 

WHERE
attempts.sumgrades/quiz.sumgrades < 1
	AND u.suspended = 0

  	AND u.firstname NOT LIKE 'demo mito%'
    AND u.firstname NOT LIKE 'mitolms%'

  	AND attempts.attempt >= 4

) as sql_embed
  
LEFT JOIN prefix_quiz_overrides AS qover 
	ON qover.quiz = sql_embed.quizid 
	AND qover.userid = sql_embed.userid 
WHERE (qover.attempts IS NULL OR qover.attempts = sql_embed.attempt)
  
 ) AS attempt_join

LEFT JOIN 
	(
    SELECT
	context.instanceid,
	ita.firstname,
	ita.lastname,
	ita.email
	FROM prefix_context AS context 
	INNER JOIN prefix_role_assignments AS ra 
		ON ra.contextid = context.id
	INNER JOIN prefix_user ita 
		ON ita.id = ra.userid 
		AND ita.email like '%@mito.org.nz' 
	) AS ita
	ON ita.instanceid = attempt_join.userid

JOIN prefix_grade_items AS gi 
	ON gi.iteminstance = attempt_join.instance 
	AND gi.courseid = attempt_join.courseid
JOIN prefix_grade_grades gg 
	ON gg.itemid = gi.id 
	AND gg.userid = attempt_join.userid 
	AND gg.finalgrade < 10
JOIN prefix_prog_user_assignment AS progua 
	ON progua.userid = attempt_join.userid 
JOIN prefix_prog AS prog ON prog.id = progua.programid
	AND prog.fullname IS NOT NULL
	AND prog.fullname != 'Business (First Line Management) (Level 4)'
JOIN prefix_enrol AS enrol 
	ON enrol.courseid = attempt_join.courseid
JOIN prefix_user_enrolments ue 
	ON ue.userid = attempt_join.userid 
		AND ue.enrolid = enrol.id 
		AND ue.status = 0
JOIN prefix_prog_courseset pcs 
	ON pcs.programid=prog.id 
JOIN prefix_prog_courseset_course pcsc 
	ON pcsc.coursesetid = pcs.id AND pcsc.courseid = attempt_join.courseid


WHERE (gg.feedback IS NULL OR gg.feedback NOT LIKE '%ON HOLD%')

GROUP BY attempt_join.attemptid

ORDER BY attempt_join.timefinish ASC, attempt_join.fullname

%%FILTER_SUBCATEGORIES:attempt_join.coursecategorypath%%