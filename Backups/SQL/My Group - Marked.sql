SELECT
SUBSTRING(uid.data, 16, 2) AS 'Group',
CONCAT('<span class="accesshide" >', CAST(attempts.timefinish as CHAR), '</span>', 
/* Adjust time to NZST or NZDT depending on week of the year*/
CASE
    WHEN (DATE_FORMAT(FROM_UNIXTIME(attempts.timefinish), '%U') > 39) THEN DATE_FORMAT(FROM_UNIXTIME(attempts.timefinish + 46800),'%d %M %Y %l:%i %p')
    WHEN (DATE_FORMAT(FROM_UNIXTIME(attempts.timefinish), '%U') < 14) THEN DATE_FORMAT(FROM_UNIXTIME(attempts.timefinish + 46800),'%d %M %Y %l:%i %p')
    ELSE DATE_FORMAT(FROM_UNIXTIME(attempts.timefinish + 43200),'%d %M %Y %l:%i %p')
END) AS 'Submitted',
CONCAT('<span class="accesshide" >', CAST(gg.timemodified as CHAR), '</span>', 
/* Adjust time to NZST or NZDT depending on week of the year*/
CASE
    WHEN (DATE_FORMAT(FROM_UNIXTIME(gg.timemodified), '%U') > 39) THEN DATE_FORMAT(FROM_UNIXTIME(gg.timemodified + 46800),'%d %M %Y %l:%i %p')
    WHEN (DATE_FORMAT(FROM_UNIXTIME(gg.timemodified), '%U') < 14) THEN DATE_FORMAT(FROM_UNIXTIME(gg.timemodified + 46800),'%d %M %Y %l:%i %p')
    ELSE DATE_FORMAT(FROM_UNIXTIME(gg.timemodified + 43200),'%d %M %Y %l:%i %p')
END) AS 'Graded',
CONCAT('<a target="_new" href = "%%WWWROOT%%/user/profile.php?id=', CAST(u.id AS CHAR), '">',u.firstname, ' ', u.lastname, '</a><hr><a target="_new" href = "https://mitocrm.mito.org.nz/main.aspx?etc=2&extraqs=formid%3d85b5f7f3-ac5a-4beb-95da-2fb3e6b50f38&id=%7b',u.idnumber,'%7d&pagetype=entityrecord">',u.username,'</a><hr>', u.phone1) As 'Learner',
CONCAT(u.department, '<hr>',u.phone2) As Employer,
CONCAT(prog.fullname,'<hr><a target="_new" href = "%%WWWROOT%%/course/view.php?id=', CAST(c.id AS CHAR), '">',CAST(c.fullname AS CHAR),'</a><hr><a target="_new" href = "%%WWWROOT%%/mod/quiz/view.php?id=', CAST(cm.id AS CHAR), '">',CAST(quiz.name AS CHAR),'</a>') AS 'Programme and course', 
CONCAT('Att: ', attempts.attempt,'<hr><a target="_new" href = "%%WWWROOT%%/mod/quiz/review.php?attempt=', CAST(attempts.id AS CHAR), '">Grade attempt</a><hr>',ROUND(gg.finalgrade*10,1)) AS 'Grade',
CONCAT('<a target="_new" href = "%%WWWROOT%%/blocks/completionstatus/details.php?course=', CAST(c.id AS CHAR), '&user=',CAST(u.id AS CHAR),'">Check course progress</a><hr><a href="mailto:', u.email, '?cc=elearning@mito.org.nz&Subject=MITO eLearning - ', c.fullname, ' ??? ', quiz.name, '&body=Kia ora ',u.firstname, '%0D%0A%0D%0AI have marked your written assessment for the course ',c.fullname,'.%0D%0A%0D%0ACongratulations! You have passed the written assessment.%0D%0A%0D%0AHere\'s a link to the marked attempt: https://elearning.mito.org.nz/mod/quiz/review.php?attempt=',CAST(attempts.id AS CHAR),'%0D%0A%0D%0AYou have now completed all of the assessments for this course.%0D%0A%0D%0APlease note you still have topic assessments for this course that you need to complete.','">Send completed email (Outlook)</a>','<hr><a target="_new" href="https://mail.google.com/mail/?view=cm&fs=1&tf=1&to=',u.email, '&cc=elearning@mito.org.nz&su=MITO eLearning - ', c.fullname, ' ??? ', quiz.name, '&body=Kia ora ',u.firstname, '%0D%0A%0D%0AI have marked your written assessment for the course ',c.fullname,'.%0D%0A%0D%0ACongratulations! You have passed the written assessment.%0D%0A%0D%0AHere\'s a link to the marked attempt: https://elearning.mito.org.nz/mod/quiz/review.php?attempt=',CAST(attempts.id AS CHAR),'%0D%0A%0D%0AYou have now completed all of the assessments for this course.%0D%0A%0D%0APlease note you still have topic assessments for this course that you need to complete.','">Send completed email (Gmail)</a>') AS 'Passed Actions', 
CONCAT('<a target="_new" href = "%%WWWROOT%%/mod/quiz/overrides.php?cmid=', CAST(cm.id AS CHAR), '&mode=user">Add override</a><hr><a href="mailto:', u.email, '?cc=elearning@mito.org.nz&Subject=MITO eLearning - ', c.fullname, ' ??? ', quiz.name, '&body=Kia ora ',u.firstname, '%0D%0A%0D%0AI have marked your written assessment for the course ',c.fullname,'.%0D%0A%0D%0AUnfortunately you have not provided sufficient answers for all of the questions.%0D%0A%0D%0AI have added comments to the assessment where you need to provide additional information.%0D%0A%0D%0AHere\'s a link to the marked attempt: https://elearning.mito.org.nz/mod/quiz/review.php?attempt=',CAST(attempts.id AS CHAR),'%0D%0A%0D%0AYou will need to start a new attempt and re-do any questions that you did not pass on your earlier attempt (you do not need to add anything to questions that were marked correct).%0D%0A%0D%0APlease reply to this email if you have any questions.','">Send failed email (Outlook)</a>','<hr><a target="_new" href="https://mail.google.com/mail/?view=cm&fs=1&tf=1&to=',u.email, '&cc=elearning@mito.org.nz&su=MITO eLearning - ', c.fullname, ' ??? ', quiz.name, '&body=Kia ora ',u.firstname, '%0D%0A%0D%0AI have marked your written assessment for the course ',c.fullname,'.%0D%0A%0D%0AUnfortunately you have not provided sufficient answers for all of the questions.%0D%0A%0D%0AI have added comments to the assessment where you need to provide additional information.%0D%0A%0D%0AHere\'s a link to the marked attempt: https://elearning.mito.org.nz/mod/quiz/review.php?attempt=',CAST(attempts.id AS CHAR),'%0D%0A%0D%0AYou will need to start a new attempt and re-do any questions that you did not pass on your earlier attempt (you do not need to add anything to questions that were marked correct).%0D%0A%0D%0APlease reply to this email if you have any questions.','">Send failed email (Gmail)</a>') AS 'Failed Actions'

FROM
(
  SELECT
  id,
  quiz,
  userid,
  attempt,
  timefinish,
  timemodified,
  sumgrades
  FROM prefix_quiz_attempts
  WHERE timemodified > (UNIX_TIMESTAMP() - 864800)
  AND timefinish != 0
  AND preview = 0
) AS attempts
JOIN prefix_quiz AS quiz 
    ON attempts.quiz = quiz.id
JOIN prefix_course_modules AS cm 
    ON cm.instance = quiz.id 
    AND cm.module = 18
JOIN prefix_user AS u 
    ON u.id = attempts.userid
JOIN prefix_course AS c 
    ON c.id = cm.course
JOIN prefix_grade_items AS gi 
    ON gi.iteminstance = attempts.quiz
    AND gi.courseid = quiz.course
JOIN
( 
    SELECT
    id,
    itemid,
    userid,
    timemodified,
    usermodified,
    finalgrade
    FROM prefix_grade_grades_history
    WHERE usermodified = %%USERID%%
    AND timemodified > (UNIX_TIMESTAMP() - 864800)
)   AS gg
        ON gg.itemid = gi.id 
        AND gg.userid = attempts.userid
        AND gg.timemodified = attempts.timemodified
JOIN prefix_prog_user_assignment AS progua 
    ON progua.userid = u.id 
LEFT JOIN prefix_prog AS prog 
    ON prog.id = progua.programid
JOIN prefix_user_info_data AS uid 
    ON uid.userid = u.id
LEFT JOIN prefix_course_modules_completion AS cmc 
    ON cmc.userid = u.id 
    AND cmc.coursemoduleid = cm.id

WHERE quiz.preferredbehaviour = 'deferredfeedback'

GROUP BY attempts.id

ORDER BY gg.timemodified DESC, u.firstname, c.fullname, quiz.name