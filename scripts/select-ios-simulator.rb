#!/usr/bin/env ruby
# frozen_string_literal: true

require 'json'

devices = JSON.parse($stdin.read).fetch('devices').values.flatten
match = devices.find do |device|
  device.fetch('isAvailable', false) && device.fetch('name', '').start_with?('iPhone')
end

abort 'No available iPhone simulator found' unless match

puts match.fetch('udid')
