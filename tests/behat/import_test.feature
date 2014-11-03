@ou @ou_vle @qtype @qtype_opaque
Feature: Test importing Opaque questions.
  In order use some questions I was given
  As an teacher
  I need to import Opaque questions.

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
    And I log in as "teacher"
    And I follow "Course 1"

  @javascript
  Scenario: import an Opaque question.
    # Import sample file.
    When I navigate to "Import" node in "Course administration > Question bank"
    And I set the field "id_format_xml" to "1"
    And I upload "question/type/opaque/tests/fixtures/testquestion.moodle.xml" file to "Import" filemanager
    And I press "id_submitbutton"
    Then I should see "Parsing questions from import file."
    And I should see "Importing 1 questions from file"
    When I press "Continue"
    Then I should see "Imported Opaque question"

    # Verify that the engine definition was imported.
    When I log out
    And I log in as "admin"
    And I navigate to "Opaque" node in "Site administration > Plugins > Question types"
    Then I should see "Test OpenMark engine (Used by 1 questions)"
