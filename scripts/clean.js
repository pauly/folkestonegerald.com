#!/usr/bin/env node

'use strict'

const fs = require('fs')
const path = require('path')
const async = require('async')
const childProcess = require('child_process')

const dir = path.join(__dirname, '../wiki')
let tasks = fs.readdirSync(dir).map(file => {
  return callback => {
    const phrase = decodeURIComponent(file.replace('.md', '').replace(/\+/g, ' '))
    const cmd = `grep -ir '${phrase}' _posts/ gig/ | grep tags | wc -l`
    childProcess.exec(cmd, (error, stdout, stderr) => {
      if (error) return callback(error)
      const matched = parseInt(stdout)
      if (matched < 2) {
        const cmd = `rm ${path.join(dir, file)}`
        console.log('-', file)
        return childProcess.exec(cmd, callback)
      }
      console.log('+', file)
      callback()
    })
  }
})

async.parallelLimit(tasks, 50, (err, results) => {
  if (err) console.error(err)
  console.log('done')
})
