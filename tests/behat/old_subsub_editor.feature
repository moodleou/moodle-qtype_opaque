@ou @ou_vle @qtype @qtype_opaque
Feature: Test an Opaque question using the legacy sub-sub editor
  In order for all our old questions to keep working
  As an OU staff member
  I we need the editadvancedfield OpenMark component to keep working in Moodle.

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
    And I am on "Course 1" course homepage
    And I navigate to "Question bank" in current page administration

  @javascript
  Scenario: Attempt a question that uses the old editor
    # Create a new question.
    And I add a "Opaque" question filling the form with:
      | Question name    | Test Opaque question |
      | Question id      | omdemo.text.q02      |
      | Question version | 1.3                  |
    Then I should see "Test Opaque question"

    # Preview it.
    When I choose "Preview" action for "Test Opaque question" in the question bank
    And I switch to "questionpreview" window
    And I set the following fields to these values:
      | Marks | Show mark and max |
    And I press "Start again with these options"
    Then I should see "Following from the previous question"
    And ".mceToolbarRow1 .mceIcon.mce_sub" "css_element" should be visible
