import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom'

import {CardFields} from "../Components/card-fields";

test('card fields component', () => {
    render(<CardFields />);
});
