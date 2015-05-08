@ou @ou_vle @qtype @qtype_opaque
Feature: Import and export Opaque questions
  As a teacher
  In order to reuse my Opaque questions
  I need to be able to import and export them

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
  Scenario: Import and export Opaque questions
    # Import sample file.
    When I navigate to "Import" node in "Course administration > Question bank"
    And I set the field "id_format_xml" to "1"
    And I upload "question/type/opaque/tests/fixtures/testquestion.moodle.xml" file to "Import" filemanager
    And I press "id_submitbutton"
    Then I should see "Parsing questions from import file."
    And I should see "Importing 1 questions from file"
    When I press "Continue"
    Then I should see "Imported Opaque question"

    # Now export again.
    When I set the field "Select a category" to "Imported questions (1)"
    And I navigate to "Export" node in "Course administration > Question bank"
    And I set the field "id_format_xml" to "1"
    And I press "Export questions to file"
    Then following "click here" should download between "950" and "1050" bytes

    # Verify that the engine definition was imported.
    When I log out
    And I log in as "admin"
    And I navigate to "Opaque" node in "Site administration > Plugins > Question types"
    Then I should see "Test OpenMark engine (Used by 1 questions)"
