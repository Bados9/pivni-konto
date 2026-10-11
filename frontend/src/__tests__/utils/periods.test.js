import { describe, it, expect } from 'vitest'
import { getDrinkingDate, getPeriodRange, getPeriodLabel, toDateString } from '../../utils/periods'

// Neděle 11. 10. 2026, 14:00
const now = new Date(2026, 9, 11, 14, 0)

function range(period, offset, at = now) {
  const { start, end } = getPeriodRange(period, offset, at)
  return [toDateString(start), toDateString(end)]
}

describe('getDrinkingDate', () => {
  it('returns the same day after 5:00', () => {
    expect(toDateString(getDrinkingDate(new Date(2026, 9, 11, 5, 0)))).toBe('2026-10-11')
  })

  it('returns the previous day before 5:00', () => {
    expect(toDateString(getDrinkingDate(new Date(2026, 9, 11, 4, 59)))).toBe('2026-10-10')
  })
})

describe('getPeriodRange', () => {
  it('shifts days back', () => {
    expect(range('today', 0)).toEqual(['2026-10-11', '2026-10-11'])
    expect(range('today', 3)).toEqual(['2026-10-08', '2026-10-08'])
  })

  it('counts the day from 5:00', () => {
    expect(range('today', 1, new Date(2026, 9, 11, 2, 0))).toEqual(['2026-10-09', '2026-10-09'])
  })

  it('uses Monday–Sunday weeks', () => {
    expect(range('week', 0)).toEqual(['2026-10-05', '2026-10-11'])
    expect(range('week', 2)).toEqual(['2026-09-21', '2026-09-27'])
  })

  it('shifts months across a year boundary', () => {
    expect(range('month', 1)).toEqual(['2026-09-01', '2026-09-30'])
    expect(range('month', 10)).toEqual(['2025-12-01', '2025-12-31'])
  })

  it('shifts years', () => {
    expect(range('year', 1)).toEqual(['2025-01-01', '2025-12-31'])
  })
})

describe('getPeriodLabel', () => {
  it('names today and yesterday, then shows the weekday', () => {
    expect(getPeriodLabel('today', 0, now)).toEqual({ title: 'Dnes', subtitle: 'ne 11. 10. 2026' })
    expect(getPeriodLabel('today', 1, now).title).toBe('Včera')
    expect(getPeriodLabel('today', 2, now)).toEqual({ title: 'Pátek 9. 10.', subtitle: '2026' })
  })

  it('labels weeks', () => {
    expect(getPeriodLabel('week', 1, now)).toEqual({ title: 'Minulý týden', subtitle: '28. 9. – 4. 10.' })
    expect(getPeriodLabel('week', 2, now)).toEqual({ title: '21. 9. – 27. 9.', subtitle: '2026' })
  })

  it('labels months and years', () => {
    expect(getPeriodLabel('month', 0, now)).toEqual({ title: 'Tento měsíc', subtitle: 'říjen 2026' })
    expect(getPeriodLabel('month', 2, now)).toEqual({ title: 'Srpen 2026', subtitle: '' })
    expect(getPeriodLabel('year', 1, now)).toEqual({ title: '2025', subtitle: 'Minulý rok' })
  })
})
