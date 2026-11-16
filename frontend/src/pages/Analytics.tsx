import { useState } from 'react'
import { PageHeader, Tabs } from '../components/ui'
import { useAnalytics } from '../lib/queries'
import { AnalyticsView } from './AnalyticsView'

export default function Analytics() {
  const [days, setDays] = useState('30')
  const { data, isLoading } = useAnalytics(Number(days))
  return (
    <>
      <PageHeader eyebrow="Insights" title="Analytics" subtitle="How your AI apps, agents and crews are performing."
        actions={<Tabs value={days} onChange={setDays} items={[{ value: '7', label: '7 days' }, { value: '30', label: '30 days' }, { value: '90', label: '90 days' }]} />} />
      <AnalyticsView data={data} loading={isLoading} />
    </>
  )
}
