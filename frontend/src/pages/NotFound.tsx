import { Link } from 'react-router'
import { Button } from '../components/ui'

export default function NotFound() {
  return (
    <div className="grid min-h-[60vh] place-items-center text-center">
      <div>
        <div className="font-display text-7xl font-black text-gradient">404</div>
        <p className="mt-2 text-sand-400">This rung of the ladder does not exist.</p>
        <Link to="/" className="mt-6 inline-block"><Button>Back to dashboard</Button></Link>
      </div>
    </div>
  )
}
