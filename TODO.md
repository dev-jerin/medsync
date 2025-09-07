# TODO: Lab Results Fixes

## Completed Tasks
- [x] Modify `doctor/api.php` to filter lab results by doctor_id in `get_lab_results` action
- [x] Update `doctor/dashboard.php` to add `type="submit"` and `form="lab-result-form"` to the save button in lab result modal

## Summary
- Fixed the issue where doctors could see all lab results instead of only their own by adding WHERE lr.doctor_id = ? in the SELECT query.
- Fixed the save button not working by making it a submit button associated with the form, since it was outside the form element.
