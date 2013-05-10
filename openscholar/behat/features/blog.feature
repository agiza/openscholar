Feature:
  Testing the blog tab.

  @api
  Scenario: Test the Blog tab
     Given I visit "john"
      When I click "Blog"
      Then I should see "First blog"

  @api
  Scenario: Test the Blog archive
    Given I visit "john"
      And I click "Blog"
      And I should see "ARCHIVE"
     When I visit "john/blog/archive/all"
      And I should see "First blog"
      And I visit "john/blog/archive/all/201301"
     Then I should see "Archive: January 2013"
      And I should not see "First blog"
