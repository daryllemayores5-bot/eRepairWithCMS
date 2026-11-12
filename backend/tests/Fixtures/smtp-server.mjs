import net from 'node:net'
import { writeFileSync } from 'node:fs'
import { randomUUID } from 'node:crypto'

// Local test sink only: no forwarding, real recipients, saved message bodies,
// credentials or external sockets. The parent owns and removes the ready file.
const readyFile = process.argv[2]
if (!readyFile) throw new Error('A parent-owned readiness file is required.')
let accepted = 0
const control = randomUUID()
// Bounded lifetime also handles an aborted parent test without OS-wide process
// inspection or taskkill, which can hang in restricted Windows sessions.
setTimeout(() => process.exit(0), 15000).unref()
function persist() { writeFileSync(readyFile, JSON.stringify({ port: server.address().port, accepted, control, pid: process.pid })) }
const server = net.createServer(socket => {
  let buffer = ''
  let receiving = false
  let bytes = 0
  socket.setEncoding('utf8')
  socket.on('error', () => {})
  socket.write('220 erepair.test ESMTP acceptance fixture\r\n')
  socket.on('data', data => {
    buffer += data
    if (buffer.length > 1048576) { socket.destroy(); return }
    let boundary
    while ((boundary = buffer.indexOf('\r\n')) !== -1) {
      const line = buffer.slice(0, boundary)
      buffer = buffer.slice(boundary + 2)
      if (receiving) {
        bytes += line.length
        if (bytes > 524288) { socket.destroy(); return }
        if (line === '.') {
          receiving = false
          accepted++
          persist()
          socket.write('250 2.0.0 captured locally\r\n')
        }
      } else if (line === `XEREPAIR-STOP ${control}`) { socket.end('221 fixture stopping\r\n'); setTimeout(() => process.exit(0), 10) }
      else if (/^(EHLO|HELO) /i.test(line)) socket.write('250-erepair.test\r\n250 SIZE 524288\r\n')
      else if (/^(MAIL FROM:|RCPT TO:|RSET|NOOP)/i.test(line)) socket.write('250 2.0.0 OK\r\n')
      else if (/^DATA$/i.test(line)) { receiving = true; bytes = 0; socket.write('354 End with a single period\r\n') }
      else if (/^QUIT$/i.test(line)) { socket.end('221 2.0.0 bye\r\n'); return }
      else socket.write('502 5.5.1 unsupported command\r\n')
    }
  })
})
server.on('error', () => { process.stderr.write('Local SMTP fixture could not start.\n'); process.exit(1) })
server.listen(0, '127.0.0.1', persist)
