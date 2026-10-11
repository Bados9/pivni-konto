// Výpočet posunutých období (den/týden/měsíc/rok) pro listování šipkami.
// Pivní den začíná v 5:00 stejně jako na backendu, takže před pátou ráno
// je "dnes" ještě předchozí den.

const DAY_START_HOUR = 5

const WEEKDAYS = ['neděle', 'pondělí', 'úterý', 'středa', 'čtvrtek', 'pátek', 'sobota']
const WEEKDAYS_SHORT = ['ne', 'po', 'út', 'st', 'čt', 'pá', 'so']
const MONTHS = [
  'leden', 'únor', 'březen', 'duben', 'květen', 'červen',
  'červenec', 'srpen', 'září', 'říjen', 'listopad', 'prosinec',
]

function capitalize(text) {
  return text.charAt(0).toUpperCase() + text.slice(1)
}

function shortDate(date) {
  return `${date.getDate()}. ${date.getMonth() + 1}.`
}

// Lokální datum jako YYYY-MM-DD (toISOString by posunul datum do UTC)
export function toDateString(date) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

export function getDrinkingDate(now = new Date()) {
  const date = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  if (now.getHours() < DAY_START_HOUR) {
    date.setDate(date.getDate() - 1)
  }
  return date
}

// Vrací první a poslední den období posunutého o `offset` zpět
export function getPeriodRange(period, offset, now = new Date()) {
  const today = getDrinkingDate(now)

  if (period === 'today') {
    const day = new Date(today)
    day.setDate(day.getDate() - offset)
    return { start: day, end: new Date(day) }
  }

  if (period === 'week') {
    const start = new Date(today)
    start.setDate(start.getDate() - ((start.getDay() + 6) % 7) - 7 * offset)
    const end = new Date(start)
    end.setDate(end.getDate() + 6)
    return { start, end }
  }

  if (period === 'month') {
    const start = new Date(today.getFullYear(), today.getMonth() - offset, 1)
    const end = new Date(start.getFullYear(), start.getMonth() + 1, 0)
    return { start, end }
  }

  const year = today.getFullYear() - offset
  return { start: new Date(year, 0, 1), end: new Date(year, 11, 31) }
}

export function getPeriodLabel(period, offset, now = new Date()) {
  const { start, end } = getPeriodRange(period, offset, now)

  if (period === 'today') {
    const named = ['Dnes', 'Včera'][offset]
    if (named) {
      return { title: named, subtitle: `${WEEKDAYS_SHORT[start.getDay()]} ${shortDate(start)} ${start.getFullYear()}` }
    }
    return { title: `${capitalize(WEEKDAYS[start.getDay()])} ${shortDate(start)}`, subtitle: String(start.getFullYear()) }
  }

  if (period === 'week') {
    const range = `${shortDate(start)} – ${shortDate(end)}`
    const named = ['Tento týden', 'Minulý týden'][offset]
    if (named) {
      return { title: named, subtitle: range }
    }
    return { title: range, subtitle: String(end.getFullYear()) }
  }

  if (period === 'month') {
    const monthName = `${MONTHS[start.getMonth()]} ${start.getFullYear()}`
    const named = ['Tento měsíc', 'Minulý měsíc'][offset]
    if (named) {
      return { title: named, subtitle: monthName }
    }
    return { title: capitalize(monthName), subtitle: '' }
  }

  return {
    title: String(start.getFullYear()),
    subtitle: ['Tento rok', 'Minulý rok'][offset] || '',
  }
}
