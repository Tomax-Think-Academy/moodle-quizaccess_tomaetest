
aws s3 cp ./TET-accessrule-moodle.zip s3://capsule.public/MoodlePlugins/ETest/TET-accessrule-moodle.zip
aws s3api put-object-acl --bucket capsule.public --key MoodlePlugins/ETest/TET-accessrule-moodle.zip --acl public-read

echo You can test the link now - https://s3.eu-west-1.amazonaws.com/capsule.public/MoodlePlugins/ETest/TET-accessrule-moodle.zip
pause
