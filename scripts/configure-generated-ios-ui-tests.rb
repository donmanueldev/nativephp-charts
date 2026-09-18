#!/usr/bin/env ruby
# frozen_string_literal: true

require 'xcodeproj'

project_path = ARGV.fetch(0) do
  warn 'usage: configure-generated-ios-ui-tests.rb /path/to/NativePHP.xcodeproj'
  exit 2
end

project = Xcodeproj::Project.open(project_path)
app_target = project.targets.find { |target| target.name == 'NativePHP-simulator' }
test_target = project.targets.find { |target| target.name == 'NativePHPUITests' }

abort 'NativePHP-simulator target not found' unless app_target
abort 'NativePHPUITests target not found' unless test_target

test_target.dependencies.each(&:remove_from_project)
test_target.add_dependency(app_target)
test_target.build_configurations.each do |configuration|
  configuration.build_settings['TEST_TARGET_NAME'] = app_target.name
  configuration.build_settings['CODE_SIGNING_ALLOWED'] = 'NO'
end

target_attributes = project.root_object.attributes['TargetAttributes'] ||= {}
test_attributes = target_attributes[test_target.uuid] ||= {}
test_attributes['TestTargetID'] = app_target.uuid
project.save

scheme = Xcodeproj::XCScheme.new
scheme.add_build_target(app_target)
scheme.add_test_target(test_target)
scheme.set_launch_target(app_target)
scheme.save_as(project_path, 'NativePHPChartsUITests', true)

puts "Configured NativePHPUITests against #{app_target.name}."
