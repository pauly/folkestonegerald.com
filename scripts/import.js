#!/usr/bin/env node

'use strict'

const fs = require('fs')
const path = require('path')
const mkdirp = require('mkdirp')
const mysql = require('mysql')
const async = require('async')
const { extractTags, htmlify, quote, slugify, tagify, urlify, ymlsafe, limit } = require('clarkeology.com')

const replaceFile = (file, content, callback) => {
  const fullPath = path.join(__dirname, file)
  fs.readFile(fullPath, 'utf8', (ignoredError, original) => {
    if (original === content) {
      console.log('-', file)
      return callback() // nothing changed
    }
    console.log('+', file)
    mkdirp(path.dirname(fullPath), () => {
      fs.writeFile(fullPath, content, 'utf8', callback)
    })
  })
}

const MINTAGLENGTH = 2
const PARALLELLIMIT = 10
const LIMIT = process.argv[2] || limit

const config = {
  host: process.env.MYSQL_HOST || 'localhost',
  user: process.env.MYSQL_USER || 'root',
  password: process.env.MYSQL_PASS,
  database: 'popex',
  connectTimeout: 60000
}

// turn an array of tags into an array of tasks for async
const makeTagTasks = occurences => {
  return Object.keys(occurences).map(tag => {
    return callback => {
      if (occurences[tag] === 1) {
        console.error(`grep -irl "${tag.replace(/\+/g, ' ')}" _posts gig | xargs -o vi`)
        return callback()
      }
      if (tag.length < MINTAGLENGTH) {
        console.error(`grep -irlE "tags: .*${tag.replace(/\+/g, ' ')}" _posts gig | xargs -o vi`)
        return callback()
      }
      const content = `---
layout: wiki
title: ${quote(tag.trim())}
tags: [${quote(tag.trim())}]
---`
      replaceFile(`../wiki/${urlify(tag)}/index.html`, content, callback)
    }
  })
}

const connection = mysql.createConnection(config)
connection.connect()

let tasks = []

tasks.push(callback => fs.mkdir(path.join(__dirname, '../wiki'), () => callback()))
tasks.push(callback => fs.mkdir(path.join(__dirname, '../_includes'), () => callback()))
tasks.push(callback => fs.mkdir(path.join(__dirname, '../venue'), () => callback()))
tasks.push(callback => fs.mkdir(path.join(__dirname, '../venue/_posts'), () => callback()))
tasks.push(callback => fs.mkdir(path.join(__dirname, '../_data'), () => callback()))
tasks.push(callback => fs.mkdir(path.join(__dirname, '../gig'), () => callback()))
tasks.push(callback => fs.mkdir(path.join(__dirname, '../gig/_posts'), () => callback()))
tasks.push(callback => fs.mkdir(path.join(__dirname, '../_posts'), () => callback()))

/* // not worth keeping this
const oldVenueLink = row => {
  const suitableTagForURL = tag => {
    if (tag === 'venue') return false
    return !/:/.test(tag)
  }
  const tagsForURL = tagify(row.tags).filter(suitableTagForURL).map(slugify).slice(0, 2).join('-')
  return `/${slugify(row.venueName)}/${tagsForURL}-venue-${row.venueID}/`
} */

const venuePermalink = row => `/v/${row && row.venueID}/`

const venueYML = row => {
  const address = ('' + row.venueAddress).replace(/(,\s*)+/g, ', ')
  let yml = `v${row.venueID}:
  name: ${quote(row.venueName)}
  address: ${quote(address)}
  permalink: ${venuePermalink(row)}`
  if (row.venuePhone) yml += `\n  phone: ${quote(row.venuePhone)}`
  if (row.venueDirections) yml += `\n  directions: ${quote(ymlsafe(htmlify(row.venueDirections)))}`
  if (row.venueHistory) yml += `\n  history: ${quote(ymlsafe(htmlify(row.venueHistory)))}`
  if (row.archived) yml += `\n  archived: 1`
  if (row.latitude) yml += `\n  lat: ${row.latitude}`
  if (row.latitude) yml += `\n  lon: ${row.longitude}`
  if (row.rating) yml += `\n  rating: ${row.venueRating}`
  if (row.lastRating) yml += `\n  lastRating: ${row.lastVenueRating}`
  if (row.attended) yml += `\n  attended: 1`
  if (row.tags) yml += `\n  tags: [${tagify(row.tags).map(quote).join(', ')}]`
  return yml
}

const attended = yml => /attended:/.test(yml)

tasks.push(callback => {
  connection.query(`select * from venue where attended != 1 order by venueID desc limit ${LIMIT}`, (err, result) => {
    if (err) return callback(err)
    const venues = result.map(row => venueYML(row))
    const venueTasks = result.map(row => {
      return callback => {
        // redirect_from: ${oldVenueLink(row)}
        const title = row.venueName || row.venueID
        const content = `---
title: ${quote(title)}
venue: v${row.venueID}
tags: [${tagify(row.tags).map(quote).join(', ')}]
permalink: ${venuePermalink(row)}
layout: venue
---
${htmlify(row.venueDescription)}`
        const date = new Date(row.dateLastAccessed * 1000)
        const yearMonthDay = date.toISOString().split('T').shift()
        replaceFile(`../venue/_posts/${yearMonthDay}-${row.venueID}.md`, content, callback)
      }
    })
    venueTasks.push(callback => {
      replaceFile(`../_data/venue.yml`, venues.filter(attended).join('\n') + '\n', callback)
    })
    async.parallelLimit(venueTasks, PARALLELLIMIT, callback)
  })
})

tasks.push(callback => {
  connection.query(`select * from event where attended != 1 and eventHighlight = 1 order by eventID desc limit ${LIMIT}`, (err, result) => {
    if (err) return callback(err)
    const eventTasks = result.map(row => {
      return callback => {
        const date = new Date(row.eventDate * 1000)
        const tags = tagify(extractTags(row.eventDetails))
        const yearMonthDay = date.toISOString().split('T').shift()
        let title = row.eventDescription || row.eventID
        if (/^re\W/i.test(title)) title = `${title} - ${row.eventID}`
        // redirect_from: ${permalink}
        let content = `---
title: ${quote(title)}
date: ${date.toISOString()}
venue: v${row.eventVenue}
categories: gig
board: 8
layout: gig`
        if (tags.length) content += `\ntags: [${tags.map(quote).join(', ')}]`
        if (row.source) content += `\nsource: ${quote(row.source)}`
        content += `
---
${htmlify(row.eventDetails)}`
        replaceFile(`../gig/_posts/${yearMonthDay}-${slugify(title)}.md`, content, err => {
          callback(err, tags)
        })
      }
    })
    async.parallelLimit(eventTasks, PARALLELLIMIT, callback)
  })
})

tasks.push(callback => {
  const sql = `select * from message where mBoard = 8 or mBoard = 5 order by mDate desc limit ${LIMIT}`
  connection.query(sql, (err, result) => {
    if (err) return callback(err)
    const messageTasks = result.map(row => {
      return callback => {
        const tags = tagify(row.mCategory)
        const date = new Date(row.mDate * 1000)
        const yearMonthDay = date.toISOString().split('T').shift()
        let permalink = null
        if (yearMonthDay < '2018-05-10') {
          permalink = `/m/${row.messageID}/`
        }
        let title = row.mSubject || row.messageID
        if (/^re\W/i.test(title)) title = `${title} - ${row.messageID}`
        // @todo post / parent / replies will be hard to manage in future
        let content = `---
title: ${quote(title)}
date: ${date.toISOString()}
post: ${row.messageID}
board: ${row.mBoard}
layout: post`
        if (row.mVenue) content += `\nvenue: v${row.mVenue}`
        if (row.mParent) content += `\nparent: ${row.mParent}`
        if (tags.length) content += `\ntags: [${tags.map(quote).join(', ')}]`
        if (permalink) content += `\npermalink: ${permalink}`
        content += `
---
${htmlify(row.mText)}
`
        replaceFile(`../_posts/${yearMonthDay}-${slugify(title)}.md`, content, err => {
          callback(err, tags)
        })
      }
    })
    async.parallelLimit(messageTasks, PARALLELLIMIT, callback)
  })
})

tasks.push(callback => {
  connection.query('select * from messageBoard where messageBoardID = 5 or messageBoardID = 8', (err, result) => {
    if (err) return callback(err)
    const boards = result.reduce((boards, row) => {
      const id = parseInt(row.messageBoardID, 10)
      boards[id] = {
        description: ymlsafe(htmlify(row.mbDescription)),
        tags: row.metaKeywords
      }
      return boards
    }, [])
    for (let i = boards.length; i >= 0; i--) {
      boards[i] = boards[i] ? `-
  description: ${quote(boards[i].description)}
  tags: [${boards[i].tags}]` : `-`
    }
    replaceFile(`../_data/board.yml`, boards.join('\n'), callback)
  })
})

async.parallel(tasks, (err, results) => {
  if (err) console.error(err)
  const occurences = results.reduce((tags, result) => {
    if (!result) return tags
    result = result.filter(arr => arr && arr.length)
    if (!result.length) return tags
    result = result.reduce((result, arr) => result.concat(arr), [])
    // return tags.concat(result)
    for (let tag of result) tags[tag] = (tags[tag] || 0) + 1
    return tags
  }, {})
  const tagTasks = makeTagTasks(occurences)
  async.parallelLimit(tagTasks, PARALLELLIMIT, err => {
    if (err) console.error(err)
    connection.end()
  })
})
