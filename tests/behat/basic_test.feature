@ou @ou_vle @qtype @qtype_opaque
Feature: Test all the basic functionality of this question type
  In order evaluate students calculating ability
  As an teacher
  I need to create and preview variable numeric questions.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "users" exist:
      | username | firstname |
      | teacher  | Teacher   |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | teacher | C1     | editingteacher |
    And I set up Opaque using the test configuration
    And I log in as "teacher"
    And I follow "Course 1"
    And I navigate to "Question bank" node in "Course administration"

  @javascript
  Scenario: Create, edit then preview an Opaque question.
    # Create a new question.
    And I add a "Opaque" question filling the form with:
      | Question name    | Test Opaque question |
      | Question id      | omdemo.text.q01      |
      | Question version | 1.2                  |
    Then I should see "Test Opaque question"

    # Preview it.
    When I click on "Preview" "link" in the "Test Opaque question" "table_row"
    And I switch to "questionpreview" window
    And I set the following fields to these values:
      | Marks | Show mark and max |
    And I press "Start again with these options"
    Then I should see "A catalyst speeds up a reaction"
    And the state of "A catalyst speeds up a reaction" question is shown as "You have 3 tries."
    When I set the field with xpath "//input[contains(@id, '_omval_input')]" to "Lubrication"
    And I press "Check"
    Then I should see "Your answer is  incorrect."
    When I press "Try again"
    Then the state of "A catalyst speeds up a reaction" question is shown as "You have 2 tries left."
    When I set the field with xpath "//input[contains(@id, '_omval_input')]" to "Heat"
    And I press "Check"
    Then I should see "Increasing temperature always speeds up a reaction."
    And the state of "A catalyst speeds up a reaction" question is shown as "Correct"
    And I should see "Mark 2.00 out of 3.00"
    And I switch to the main window

    # Backup the course and restore it.
    When I log out
    And I log in as "admin"
    When I backup "Course 1" course using this options:
      | Confirmation | Filename | test_backup.mbz |
    When I restore "test_backup.mbz" backup into a new course using this options:
      | Schema | Course name | Course 2 |
    Then I should see "Course 2"
    When I navigate to "Question bank" node in "Course administration"
    Then I should see "Test Opaque question"

    # Edit the copy and verify the form field contents.
    When I click on "Edit" "link" in the "Test Opaque question" "table_row"
    Then the following fields match these values:
      | Question name    | Test Opaque question |
      | Question id      | omdemo.text.q01      |
      | Question version | 1.2                  |
    And I set the following fields to these values:
      | Question name | Edited question name |
    And I press "id_submitbutton"
    Then I should see "Edited question name"

    # Verify that the engine definition was reused, not duplicated.
    When I navigate to "Opaque" node in "Site administration > Plugins > Question types"
    Then I should see "Opaque engine for tests (Used by 2 questions)"
